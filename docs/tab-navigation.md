# Tab navigation

Adds "Previous tab" / "Next tab" buttons at the bottom of each tab of any EasyAdmin tabbed form
(`FormField::addTab()`) on the new/edit pages, so users don't have to scroll back up to the tab
bar to move on. Works on any `AbstractCrudController` — no trait, no base class, no per-controller
code required. Disabled by default; enable via:

```yaml
# config/packages/coop_tilleuls_ea_extensions.yaml
coop_tilleuls_ea_extensions:
    tab_navigation:
        enabled: true
```

## Consumer app setup (one-time)

Only the [shared setup](../README.md#setup-one-time-shared-by-every-feature) (Twig and JavaScript) — it provides the
`coop-tilleuls--ea-extensions--tab-navigation` Stimulus controller and its stylesheet.

## Behavior

- The "Previous tab" button is hidden on the first tab, "Next tab" on the last one, and both on a
  form with a single tab — in CSS first (no flash before the JS kicks in), then kept up to date by
  the controller.
- Tabs hidden on the fly (a `d-none` class toggled on a `.tab-pane`, e.g. by one of the
  [JS controllers](js-controllers/README.md)) are skipped, and the first/last tab recomputed.
- Clicking a button shows the target tab through Bootstrap's own Tab API (exactly like clicking
  its link in the tab bar), then scrolls the tab bar back into view.
- Two forms on the same page sharing tab ids (e.g. one opened in a modal) never drive each other.

## How it's wired

When enabled, the bundle appends `@EAExtensions/tab_navigation/form_theme.html.twig` to every
CRUD's form themes (on EasyAdmin's `BeforeCrudActionEvent`, i.e. after your own
`configureCrud()` form themes are set). Appended last, it has the highest priority, so it only
replaces EasyAdmin's `ea_form_tabpane_group_open_row` and `ea_form_tabpane_close_row` blocks —
every other block of your own form themes keeps applying.

If your own form theme also overrides those two blocks, the bundle's version wins. Override the
bundle's template instead (`templates/bundles/EAExtensionsBundle/tab_navigation/form_theme.html.twig`)
to customize the buttons.

## Enabling it on some CRUDs only

Leave the feature disabled and add the form theme yourself on the CrudControllers that need it —
the Stimulus controller and its stylesheet ship with the JS entrypoint either way:

```php
public function configureCrud(Crud $crud): Crud
{
    return $crud->addFormTheme('@EAExtensions/tab_navigation/form_theme.html.twig');
}
```
