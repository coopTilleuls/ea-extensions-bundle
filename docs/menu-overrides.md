# Menu overrides

Fixes two long-standing gaps in EasyAdmin's own menu handling:

- **Infinite depth**: EasyAdmin's `MenuFactory` only ever builds two levels (top-level items and
  their direct sub-items) — any sub-item that is itself a submenu has its own sub-items silently
  discarded. This bundle replaces it with a genuinely recursive builder, and ships a recursive
  Twig macro to render the result to whatever depth you declare.
- **Disambiguating identical destinations**: when two different menu items point to the exact
  same controller/action or route, EasyAdmin has no way to tell them apart — whichever one is
  listed first always gets marked "selected", regardless of which one the user actually clicked.
  A small helper lets you tag each ambiguous item individually, right where it's declared.

Enabled by default; disable via:

```yaml
# config/packages/coop_tilleuls_ea_extensions.yaml
coop_tilleuls_ea_extensions:
    menu_overrides:
        enabled: false
```

## Nesting a menu deeper than 2 levels

Just nest `MenuItem::subMenu()` inside another one's `setSubItems()` as deep as you like —
EasyAdmin's own DTO layer already supports it, only the factory used to truncate it:

```php
public function configureMenuItems(): iterable
{
    $level2 = MenuItem::subMenu('Level 2')->setSubItems([
        MenuItem::linkTo(SomeCrudController::class, 'Deeply nested'),
    ]);

    yield MenuItem::subMenu('Level 1')->setSubItems([$level2]);
}
```

The bundle does **not** override EasyAdmin's own `@EasyAdmin/menu.html.twig` template — doing so
would fight with any theme that already overrides it. Instead, render the bundle's template
directly wherever your own menu template currently loops over items, passing it the list to
render as `items`:

```twig
{% if subItem.hasSubItems %}
    {% include '@EAExtensions/menu_overrides/recursive_submenu.html.twig' ignore missing with {items: subItem.subItems} only %}
{% endif %}
```

`ignore missing` makes this a no-op when the bundle isn't installed, so a shared theme can use
this line unconditionally without depending on the bundle being present. Don't reach for
`{% if template_exists(...) %}{% import ... as menu_overrides %}{% endif %}` instead: Twig's
`{% import %}` does not leak the imported variable out of the `{% if %}` block the way `{% set %}`
does, even when the condition is true, so `menu_overrides` would never actually be defined outside
it.

If your own theme needs the extra depth styled to match its own design instead of the bundle's
plain markup, keep your theme's own rendering and just make sure it recurses into
`subItems` at every level (the earlier truncation was purely in `MenuFactory`, not in the DTOs).

## Disambiguating two items with the same destination

Tag each ambiguous item with a distinct key at the point it's declared:

```php
use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\MenuItemTag;

public function configureMenuItems(): iterable
{
    $pendingTransfers = MenuItem::linkTo(MemberCrudController::class, 'Transfers pending validation');
    MenuItemTag::new($pendingTransfers, 'pending_transfers');

    $allMembers = MenuItem::linkTo(MemberCrudController::class, 'Members');
    MenuItemTag::new($allMembers, 'all_members');

    yield $allMembers;
    yield $pendingTransfers;
}
```

There is no separate registry mapping controllers to tags to keep in sync — each item carries its
own tag, so there's nothing to forget when a new controller is added later. Under the hood, the
tag is just baked into the item's generated URL as an ordinary route parameter, and preserved
across any link EasyAdmin regenerates from that page (edit/detail/etc.), so the right item stays
highlighted even after navigating away from its own index page.

Tagging only prevents ambiguity for the exact-URL-match pass. EasyAdmin also has a looser
CRUD-index fallback for pages with no exact match (e.g. an edit/detail page, which highlights its
CRUD's index entry) — this bundle's matcher runs that fallback only once, for the whole menu, and
only if nothing matched exactly anywhere. If it ran per tree level instead (as EasyAdmin's own
matcher does), an *untagged* item sitting one level up from a correctly-tagged, exactly-matched
leaf would still get selected too — the fallback only compares plain paths, which two same-CRUD
items always share.

## Highlighting a menu item from a link you generate yourself

A menu item's own URL gets tagged via `MenuItemTag::new()` above, but sometimes the link that
should land on it isn't built from the menu item at all — an action in a `CrudController`
building a redirect, an embedded field link, a custom button. Tag that URL the same way, with the
same key, using whichever of these fits how you're building it:

```php
use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\MenuItemTag;

// building through AdminUrlGeneratorInterface
$url = MenuItemTag::applyTo(
    $this->adminUrlGenerator->setController(MemberCrudController::class),
    'pending_transfers',
)->generateUrl();

// tagging an already-built URL string (e.g. in a template)
$url = MenuItemTag::appendToUrl($url, 'pending_transfers');
```

Landing on that URL then highlights whichever menu item was tagged `pending_transfers` — exact
match, same mechanism as any other tagged menu item, nothing controller-specific involved.

## A controller with no menu entry of its own

Some CRUD controllers are never directly linked from the menu — a sub-resource only reached
through another entity's pages (e.g. a `TransferCrudController` for rows managed under a
`MemberCrudController` page). Without anything extra, visiting any of its pages highlights
nothing, because the CRUD-index fallback has no menu item for *this* controller to find.

Implement `MenuHighlightAliasInterface` on that controller to declare which other controller's
index entry should be highlighted instead:

```php
use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\MenuHighlightAliasInterface;

final class TransferCrudController extends AbstractCrudController implements MenuHighlightAliasInterface
{
    public static function getMenuHighlightAlias(): string
    {
        return MemberCrudController::class;
    }
}
```

As with `MenuItemTag`, this is declared on the controller that needs it — there's no separate
registry of controller-to-controller overrides to keep in sync, and nothing to forget when a new
sub-resource controller is added later. It only affects the CRUD-index fallback: if one of your
menu items happens to link to `TransferCrudController` directly (or it's reached via a
`MenuItemTag`-ged link), that exact match still wins over the alias.

## Which mechanism for which case

- **A menu item's own destination is ambiguous** (another menu item points to the same
  controller/action or route) → `MenuItemTag::new()` on the menu item(s).
- **You're generating a link yourself** to a destination that should highlight a `MenuItemTag`-ged
  menu item → `MenuItemTag::applyTo()`/`appendToUrl()` on that link.
- **A whole controller has no menu item at all** and should always highlight another controller's
  index entry when nothing else matches → `MenuHighlightAliasInterface` on that controller.
