# CrudFields

Embeds another CrudController's index listing inside the current page — typically a detail
page, e.g. a Book's "Reviews" tab showing the review list filtered by that book. Works on any
target `AbstractCrudController` — no trait, no base class, no per-controller code required,
including hiding that controller's own actions (see below).

## Consumer app setup (one-time)

On top of the [shared setup](../README.md#setup-one-time-shared-by-every-feature) (Twig and JavaScript):

Call `CrudFieldLayout::applyIfEmbedded()` once from your `DashboardController`'s own
   `configureCrud()`, so an embed's fetch strips the sidebar/topbar/full-page chrome while
   keeping the target controller's own `crud/index` template (vendor's, or your theme's own
   override) rendering 100% normally — table, action buttons/dropdowns, pagination and filters
   button all keep their real markup/CSS/icons. This is a one-time, app-wide setup step — not
   something every embeddable CrudController needs to declare:
   ```php
   use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudFieldLayout;

   final class DashboardController extends AbstractDashboardController
   {
       public function __construct(private readonly RequestStack $requestStack) {}

       public function configureCrud(): Crud
       {
           return CrudFieldLayout::applyIfEmbedded(Crud::new(), $this->requestStack->getCurrentRequest());
       }
   }
   ```

## Basic usage

```php
CrudField::new('reviews', 'Reviews')
    ->setController(ReviewCrudController::class)
    ->lazy()
    ->onlyOnDetail();
```

## Filtering the embed's fetch and the "voir tout" link

`overrideIndexUrl()` filters the embed's own fetch (e.g. by the current entity).
`addIndexAction()` adds a "Voir tout" link to the embed's header. By default "Voir tout"
reuses whatever `overrideIndexUrl()` resolved to, not the bare unfiltered index — so it always
shows "the rest of what you're already looking at". Use `setSeeAllUrl()` when the two should
genuinely differ:

```php
CrudField::new('reviews', 'Reviews')
    ->setController(ReviewCrudController::class)
    ->overrideIndexUrl(
        $this->adminUrlGenerator
            ->setController(ReviewCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            // EasyAdmin's own comparison-based filters (EntityFilter, TextFilter, NumericFilter,
            // DateTimeFilter...) expect this {comparison, value} shape, not a flat scalar.
            ->set('filters[book]', ['comparison' => '=', 'value' => $book->getId()])
            ->generateUrl()
    )
    ->addIndexAction();
```

## Adding domain actions

`addAction(?Action $action)` appends an arbitrary EasyAdmin action to the embed's header.
Passing `null` is a no-op, so a condition can be inlined: `->addAction($canAdd ? $action : null)`.

## Hiding actions on the embedded controller — with zero changes to that controller

`hideGlobalActions()`/`hideEntityActions()` hide the embed's global/batch actions or
per-row actions. This works automatically on **any** target controller, driven purely by two
query flags the embed's fetch URL carries — no interface, no `configureActions()` change,
nothing to declare on the target controller.

## Display options

- `displayAsCard()` — wraps the embed in a bootstrap card.
- `lazy()` — defers loading until the embed becomes visible (or "Charger" is clicked), with a
  spinner and a tab-visibility-aware fallback.
- `showFilters()` — surfaces the target controller's own filters inside the embed, applied via
  AJAX without leaving the page. The "Filtrer" button/reset link are relocated into the
  CrudField's own header, next to "Voir tout"/`addAction()` buttons, rather than staying inside
  the part of the embed that gets replaced wholesale on each refresh.

## Opening action links in a modal

`openActionsInModal()` opens the embed's own action links (new/edit, or any custom action added
via `addAction()`) in a modal instead of navigating away, submitting them via AJAX and
refreshing the embed in place on success — with, as with the rest of this feature, zero changes
required on the target controller:

```php
CrudField::new('reviews', 'Reviews')
    ->setController(ReviewCrudController::class)
    ->openActionsInModal();
```

Detail always navigates normally (it's meant to open the full page), "voir tout" always
navigates to the full index, and delete keeps using EasyAdmin's own native confirmation-modal
flow — none of those three are affected by this option.

## Restricting columns

`setCrudColumns(['title', 'author'])` restricts which columns the embed renders, by delegating
to the [customizable columns](customizable-columns.md) feature's own `eaColumns` query
contract. If that feature is disabled, this degrades gracefully to no column filtering — it's
not a hard dependency.
