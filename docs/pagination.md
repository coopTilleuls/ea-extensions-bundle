# Configurable pagination

Lets users pick how many rows show per page on any CRUD's index page, via a `<select>`
next to the pagination links. Works on any `AbstractCrudController` — no trait, no base
class, no per-controller code required. Disabled by default; enable via:

```yaml
# config/packages/coop_tilleuls_ea_extensions.yaml
coop_tilleuls_ea_extensions:
    pagination:
        enabled: true
```

## Configuration

```yaml
coop_tilleuls_ea_extensions:
    pagination:
        query_parameter: itemsPerPage   # the query-string key read/written by the selector
        default_page_size: 25           # applied when a controller declares no default of its own
        available_page_sizes: [10, 25, 50, 100]
        max_page_size: null             # optional hard cap; any requested/declared value above it is clamped
```

`default_page_size` must be one of `available_page_sizes` — otherwise the selector never shows
the size that's actually applied (no `<option>` matches it), and the bundle refuses to boot
with an `InvalidConfigurationException`.

No preference is persisted between requests — same request-driven behavior on every page
load, nothing stored per user or per session. A requested page size that isn't a positive
integer (missing, negative, zero, non-numeric) is ignored and the resolved default applies
instead — it never reaches EasyAdmin's own paginator, which would otherwise throw.

## Consumer app setup (one-time)

On top of the [shared setup](../README.md#setup-one-time-shared-by-every-feature) (Twig and
JavaScript — the page-size `<select>` is driven by a Stimulus controller), create these two plain
Symfony bundle-template overrides in your app — no PHP involved, and
both a one-liner pointing at a template this bundle ships:

```twig
{# templates/bundles/EasyAdminBundle/crud/paginator.html.twig #}
{{ include('@EAExtensions/pagination/paginator.html.twig') }}
```

```twig
{# templates/bundles/EasyAdminBundle/components/Pagination.html.twig #}
{% extends '@EAExtensions/pagination/Pagination.html.twig' %}
```

Twig resolves `@EasyAdmin/crud/paginator.html.twig` (the template EasyAdmin's own
`crud/index.html.twig` includes) and `@EasyAdmin/components/Pagination.html.twig` (the
`<twig:ea:Pagination>` component's own template) by checking your app's
`templates/bundles/EasyAdminBundle/` directory first — the same mechanism used for
overriding any vendor bundle template. This is the same category of one-time, app-wide setup as the importmap entry
required by `customizable-columns` (see
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

When both methods return a non-empty, non-null value, the default must be one of the
available sizes — same rule as the bundle-wide config, checked per request since either
method can vary its answer. A mismatch throws a `LogicException` naming the controller.

## Interaction with CrudFields

The selector automatically hides itself inside an embedded CrudFields listing (the same
`cleanLayout` query flag used to detect an embed) — no configuration needed to combine the
two features.
