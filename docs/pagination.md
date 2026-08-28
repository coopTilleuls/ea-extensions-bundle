# Configurable pagination

Lets users pick how many rows show per page on any CRUD's index page, via a `<select>`
next to the pagination links. Works on any `AbstractCrudController` — no trait, no base
class, no per-controller code required. Enabled by default; disable via:

```yaml
# config/packages/coop_tilleuls_ea_extensions.yaml
coop_tilleuls_ea_extensions:
    pagination:
        enabled: false
```

## Configuration

```yaml
coop_tilleuls_ea_extensions:
    pagination:
        query_parameter: itemsPerPage   # the query-string key read/written by the selector
        default_page_size: 20           # applied when a controller declares no default of its own
        available_page_sizes: [10, 25, 50, 100]
        max_page_size: null             # optional hard cap; any requested/declared value above it is clamped
```

No preference is persisted between requests — same request-driven behavior on every page
load, nothing stored per user or per session. A requested page size that isn't a positive
integer (missing, negative, zero, non-numeric) is ignored and the resolved default applies
instead — it never reaches EasyAdmin's own paginator, which would otherwise throw.

## Consumer app setup (one-time)

Create `templates/bundles/EasyAdminBundle/crud/paginator.html.twig` in your app — a plain
Symfony bundle-template override, no PHP involved:

```twig
{{ include('@EAExtensions/pagination/paginator.html.twig') }}
```

Twig resolves `@EasyAdmin/crud/paginator.html.twig` (the template EasyAdmin's own
`crud/index.html.twig` includes) by checking your app's `templates/bundles/EasyAdminBundle/`
directory first — this is the same mechanism used for overriding any vendor bundle
template, no bundle-specific API or one-time method call needed. This is the same category
of one-time, app-wide setup as the importmap entry required by `customizable-columns` (see
[`docs/customizable-columns.md`](customizable-columns.md)) — not something every
CrudController needs to declare.

## Optional per-controller fine-tuning

Implement `CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\PaginationConfigurationInterface`
to declare a different default and/or a different selector list for one controller:

```php
final class JournalCrudController extends AbstractCrudController implements PaginationConfigurationInterface
{
    public static function getDefaultPageSize(AdminContext $context): ?int
    {
        return 50;
    }

    public static function getAvailablePageSizes(AdminContext $context): ?array
    {
        return [50, 100, 200];
    }
}
```

Both methods receive the current `AdminContext`, so a controller can vary its answer by
request — e.g. a bigger page size only when acting as an autocomplete source:

```php
public static function getDefaultPageSize(AdminContext $context): ?int
{
    return 'autocomplete' === $context->getRequest()->query->get('crudAction') ? 1000 : null;
}
```

Returning `null` from either method falls back to the bundle-wide config value. Returning
an **empty array** from `getAvailablePageSizes()` locks the page size: the selector is
hidden and the query-string override is ignored, so the controller's own
`getDefaultPageSize()` (or the bundle default, if that's also `null`) always applies.

## Interaction with CrudFields

The selector automatically hides itself inside an embedded CrudFields listing (the same
`cleanLayout` query flag used to detect an embed) — no configuration needed to combine the
two features.
