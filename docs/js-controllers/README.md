# JS controllers

Standalone Stimulus controllers shipped by this bundle, meant to be reused directly in your own
EasyAdmin form templates — unlike the [features](../../README.md#features) above, none of these
require any PHP-side setup beyond the one-time importmap entry below.

## Consumer app setup (one-time)

Add an importmap entry for the bundle's JS entrypoint, if not already done for another feature
in this bundle (see [customizable columns](../customizable-columns.md#consumer-app-setup-one-time)):

```php
// importmap.php
'ea-extensions' => [
    'path' => 'bundles/eaextensions/ea-extensions.js',
    'entrypoint' => true,
],
```

## Available controllers

- [`refresh-choices-on-related-field-change`](refresh-choices-on-related-field-change.md) —
  refreshes a TomSelect-enhanced autocomplete field's choices whenever one or several other
  fields change, keeping only the results every listened field agrees on.
- [`refresh-values-on-related-field-change`](refresh-values-on-related-field-change.md) —
  refreshes a TomSelect-enhanced autocomplete field's choices whenever one related field changes,
  with an optional fallback field and finer-grained behavior (auto-select on a single required
  result, merge-vs-reset on first load, an always-on extra filter).
- [`toggle-display-on-field-change`](toggle-display-on-field-change.md) — shows or hides a field
  depending on whether another field's current value is in a whitelist or blacklist.
- [`toggle-display-on-multiple-select`](toggle-display-on-multiple-select.md) — shows a field only
  once another multi-select field has at least a minimum number of items selected.
- [`display-field-on-boolean-field-change`](display-field-on-boolean-field-change.md) — shows or
  hides a field depending on a boolean (checkbox/switch) field.
- [`select-all-options`](select-all-options.md) — selects every available option on a multi-select
  field from a sibling button.
- [`add-button-select`](add-button-select.md) — adds an "Add" button next to a multi-select field
  that opens its dropdown.
