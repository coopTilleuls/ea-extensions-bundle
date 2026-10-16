# Back to top

Adds a floating "Back to top" button at the bottom right of every EasyAdmin page (dashboard, CRUD
and custom admin routes alike), shown only once the page is scrolled past a threshold. No layout
override needed. Disabled by default; enable via:

```yaml
# config/packages/coop_tilleuls_ea_extensions.yaml
coop_tilleuls_ea_extensions:
    back_to_top:
        enabled: true
        threshold: 300   # optional: scroll offset (px) past which the button shows, default 300
```

## Consumer app setup (one-time)

Only the [shared setup](../README.md#setup-one-time-shared-by-every-feature) (Twig and JavaScript).
It provides the `coop-tilleuls--ea-extensions--back-to-top` Stimulus controller and its stylesheet.

## Behavior

- The button is rendered hidden and shows up once the page is scrolled past `threshold`; clicking
  it smoothly scrolls back to the top.
- The first time it shows, a `ea-extensions-back-to-top-space-reserved` class is added to `<body>`,
  reserving some space under `.content-wrapper` so the button never covers the last row of the
  page. It's never removed afterwards, so the page height doesn't jump back and forth around the
  threshold.
- It sits below Bootstrap's modal backdrop (`z-index: 1030`), so open modals cover it.
- An embedded [CrudFields](crud-fields.md) listing never adds a second button to its host page.

## How it's wired

When enabled, the bundle renders `@EAExtensions/back_to_top/button.html.twig` into the current
`AdminContext`'s assets on `kernel.controller` — the same mechanism as EasyAdmin's own
`Assets::addHtmlContentToBody()`, output by the layout's `configured_body_contents` block. It
therefore shows up on every page extending `@EasyAdmin/layout.html.twig` (or a theme layout
extending it), but not on pages that don't (e.g. EasyAdmin's default welcome page).

Override `templates/bundles/EAExtensionsBundle/back_to_top/button.html.twig` to customize the
button (icon, classes...), and `.ea-extensions-back-to-top` in your own CSS to move it.

## Pages outside EasyAdmin

For a page that isn't served by an EasyAdmin dashboard (no `AdminContext`) but still loads the
`ea-extensions` entrypoint, include the button yourself — this works whether the feature is
enabled or not:

```twig
{{ include('@EAExtensions/back_to_top/button.html.twig', {threshold: 300}) }}
```

`threshold` is optional there (the controller defaults to 300px). Note that the space reserved at
the bottom of the page targets EasyAdmin's `.content-wrapper` element.

## Screenshot tests

The button shows up as soon as a screenshot tool scrolls the page to capture an element further
down. Hide it in your screenshot stylesheet if that makes snapshots flaky:

```css
.ea-extensions-back-to-top {
    display: none !important;
}
```
