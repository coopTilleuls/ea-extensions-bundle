# `refresh-values-on-related-field-change`

Keeps a TomSelect-enhanced EasyAdmin [autocomplete field](https://symfony.com/bundles/EasyAdminBundle/current/fields.html) (any field with `->autocomplete()` called on it) in sync with one related field on the same form, with an optional fallback field used only while the primary one is empty: whenever the listened field changes, the target field's choices are re-fetched from its own autocomplete endpoint, filtered by the listened field's current value.

See [JS controllers](README.md) for the one-time consumer app setup.

The controller reads the target field's own autocomplete endpoint URL from the
`data-ea-autocomplete-endpoint-url` attribute EasyAdmin's autocomplete field already renders on
it — nothing extra to configure there. It only touches the target field's *choices* (the options
TomSelect offers), except for the "single required result" auto-select behavior described below.

## Targets

| Target  | Element                                |
|---------|------------------------------------------|
| `field` | the autocomplete `<select>` to refresh |

## Values

| Value             | Type    | Required | Default          | Description                                                                                                  |
|-------------------|---------|----------|-------------------|------------------------------------------------------------------------------------------------------------------|
| `listen`          | String  | yes      | —                 | Name of the related field to listen to.                                                                      |
| `listenFallback`  | String  | no       | —                 | Name of a related field used to refresh the target while `listen`'s field is empty.                          |
| `extraFilter`     | String  | no       | —                 | Name of a related field whose current value is always sent as an extra, unconditional filter.                |
| `filterName`      | String  | no       | `filtered_id[]`   | Query-string key sent with the listened field's selected value(s).                                           |
| `comparisonEqual` | String  | no       | —                 | When set, also sends `?<comparisonEqual>=%3D` on every fetch.                                                |
| `defaultEnable`   | Boolean | no       | `false`           | Keep the target enabled (without filtering) while the listened field is empty, instead of disabling it.      |
| `emptyOnLoad`     | Boolean | no       | `true`            | Drop the target's pre-existing options on first load instead of merging them with the freshly fetched ones.  |

`listen`, `listenFallback` and `extraFilter` are all matched against the DOM the same way:
`foo` matches `form[foo]`, `form[foo][]`, `form[foo][autocomplete]` or `form[foo][autocomplete][]`.

## Example

```html
<div data-controller="coop-tilleuls--ea-extensions--refresh-values-on-related-field-change"
     data-coop-tilleuls--ea-extensions--refresh-values-on-related-field-change-listen-value="authorA"
     data-coop-tilleuls--ea-extensions--refresh-values-on-related-field-change-listen-fallback-value="authorB">
    <select name="Book[publisher]"
            data-coop-tilleuls--ea-extensions--refresh-values-on-related-field-change-target="field"
            data-ea-autocomplete-endpoint-url="/admin/publisher/autocomplete">
        <!-- ... -->
    </select>

    <select name="Book[authorA]"><!-- ... --></select>
    <select name="Book[authorB]"><!-- ... --></select>
</div>
```

Here, `publisher`'s choices are re-fetched from `authorA`'s current selection (falling back to
`authorB` while `authorA` is empty), so it only offers the publisher(s) usually associated with
that author.

Equivalent EasyAdmin field configuration:

```php
AssociationField::new('publisher')
    ->autocomplete()
    ->setFormTypeOption('row_attr', [
        'data-controller' => 'coop-tilleuls--ea-extensions--refresh-values-on-related-field-change',
        'data-coop-tilleuls--ea-extensions--refresh-values-on-related-field-change-listen-value' => 'authorA',
        'data-coop-tilleuls--ea-extensions--refresh-values-on-related-field-change-listen-fallback-value' => 'authorB',
    ])
    ->setHtmlAttribute('data-coop-tilleuls--ea-extensions--refresh-values-on-related-field-change-target', 'field')
```

## Auto-select on a single required result

If the target field is `required` and, on the target's initial (non-search) load, its endpoint
returns exactly one result, that result is automatically selected and the field is locked
(TomSelect's `lock()`) rather than left for the user to pick from a one-item list.

## Select-all button

If the target's `<select>` sits next to a sibling element with the `.select-all` class (a
"select all" button for a multi-select), that button is enabled/disabled together with the
target field itself.

## `filterName` isn't limited to "filter to matches"

The endpoint decides what a given `filtered_id[]` (or custom `filterName`) value means — the
controller only ever sends the listened field's current value(s) under that query-string key, it
has no opinion on how the endpoint should filter with it. A custom `filterName` can just as well
implement an *exclusion*:

```html
<div data-controller="coop-tilleuls--ea-extensions--refresh-values-on-related-field-change"
     data-coop-tilleuls--ea-extensions--refresh-values-on-related-field-change-listen-value="authorA"
     data-coop-tilleuls--ea-extensions--refresh-values-on-related-field-change-filter-name-value="exclude_id[]"
     data-coop-tilleuls--ea-extensions--refresh-values-on-related-field-change-default-enable-value="true">
    <select name="Book[authorB]"
            data-coop-tilleuls--ea-extensions--refresh-values-on-related-field-change-target="field"
            data-ea-autocomplete-endpoint-url="/admin/author/autocomplete">
        <!-- ... -->
    </select>

    <select name="Book[authorA]"><!-- ... --></select>
</div>
```

Here, `authorB`'s own autocomplete endpoint reads `exclude_id[]` and filters authors *out*
instead of *in*, so `authorB` never offers whichever author is currently selected as `authorA` —
useful for "pick a second, different X" fields. `defaultEnable` is set to `true` so `authorB`
stays pickable even while `authorA` is still empty (there's nothing to exclude yet, not "nothing
to show yet").

> :warning: The bundle doesn't include filtering/exclusion process; it simply calls a URL with the pre-defined parameters. You have to implement this yourself in the relevant crud controller

## Note on `listenFallback`

The fallback field's own change handler decides whether to act based on the `listen` field's
value **as it was when the controller connected**, not its live, current value. In practice this
means: if the `listen` field starts empty and is later filled in by the user, changing the
`listenFallback` field afterwards may still trigger a refresh driven by the fallback value
instead of being ignored. Prefer wiring `listenFallback` only for fields whose "empty at load"
state is unlikely to change during the same form session.
