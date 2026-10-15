# Index-only search

Hides the search box in the layout's top bar everywhere but on index (list) pages — EasyAdmin
only lets a CRUD enable or disable its search as a whole (`Crud::setSearchFields(null)`), for all
of its pages, so it otherwise also shows on detail/edit/new pages, where it has nothing to search.
Works on any `AbstractCrudController` — no trait, no base class, no per-controller code
required. Disabled by default; enable via:

```yaml
# config/packages/coop_tilleuls_ea_extensions.yaml
coop_tilleuls_ea_extensions:
    index_only_search:
        enabled: true
```

No other setup: no template override, no JS.

## Behavior

The search box shows only when the CRUD's current page is `Crud::PAGE_INDEX` at the time the page
renders:

| Page | Search box |
|---|---|
| index | shown (if the CRUD's search is enabled at all) |
| detail, edit, new | hidden |
| custom action switching the page to index (`CrudDto::setPageName(Crud::PAGE_INDEX)`), e.g. a custom list | shown |
| any other custom action | hidden |

Searching itself is untouched: the index's own search query and association autocompletes run
off the `SearchDto` EasyAdmin builds before any action runs, not off the CRUD's search fields this
feature hides.

## How it's wired

On a non-index page, the CRUD's search fields are set to `null` (what `isSearchEnabled()`, read by
the layout, checks) and their original value kept on the request, to be restored if the page
switches to index later on. The page name is checked on `BeforeCrudActionEvent` and again right
before EasyAdmin renders an action's `KeyValueStore` (`kernel.view`). A custom action that renders
its own `Response` without dispatching `BeforeCrudActionEvent` is therefore not covered: its search
box stays as configured.
