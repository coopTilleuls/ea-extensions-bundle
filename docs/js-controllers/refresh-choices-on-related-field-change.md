# `refresh-choices-on-related-field-change`

Keeps a TomSelect-enhanced EasyAdmin [autocomplete field](https://symfony.com/bundles/EasyAdminBundle/current/fields.html) (any field with `->autocomplete()` called on it) in sync with one or several other fields on the same form: whenever a listened field changes, the target field's choices are re-fetched from its own autocomplete endpoint, then filtered down to the intersection of what every listened field's own endpoint returns for its current selection.

See [JS controllers](README.md) for the one-time consumer app setup.

The controller reads the target field's own autocomplete endpoint URL from the
`data-ea-autocomplete-endpoint-url` attribute EasyAdmin's autocomplete field already renders on
it — nothing extra to configure there. It only touches the target field's *choices* (the options
TomSelect offers), never its selected value.

## Targets

| Target  | Element                                |
|---------|------------------------------------------|
| `field` | the autocomplete `<select>` to refresh |

## Values

| Value             | Type   | Required | Default          | Description                                                                          |
|-------------------|--------|----------|-------------------|----------------------------------------------------------------------------------------|
| `listen`          | Array  | yes      | —                 | Names of the related fields to listen to.                                             |
| `filterName`      | String | no       | `filtered_id[]`   | Query-string key sent, for every listened field, with that field's selected id(s).    |
| `comparisonEqual` | String | no       | —                 | When set, also sends `?<comparisonEqual>=%3D` on every fetch.                         |

A `listen` entry is matched against the DOM by name: `foo` matches `form[foo]`, `form[foo][]`,
`form[foo][autocomplete]` or `form[foo][autocomplete][]`.

## Example

```html
<div data-controller="coop-tilleuls--ea-extensions--refresh-choices-on-related-field-change"
     data-coop-tilleuls--ea-extensions--refresh-choices-on-related-field-change-listen-value='["authorA", "authorB"]'>
    <select name="Book[serie]"
            data-coop-tilleuls--ea-extensions--refresh-choices-on-related-field-change-target="field"
            data-ea-autocomplete-endpoint-url="/admin/serie/autocomplete">
        <!-- ... -->
    </select>

    <select name="Book[authorA]"><!-- ... --></select>
    <select name="Book[authorB]"><!-- ... --></select>
</div>
```

Here, `serie` (a book series that can be co-written by several authors) only offers series that
both `authorA`'s and `authorB`'s autocomplete endpoints return for their respective current
selection — in other words, series co-written by both of them. The target is disabled (and its
choices cleared) as soon as any listened field is empty, or as soon as any listened field's own
endpoint returns no result — so `serie` stays disabled until both authors are picked.

Equivalent EasyAdmin field configuration:

```php
AssociationField::new('serie')
    ->autocomplete()
    ->setFormTypeOption('row_attr', [
        'data-controller' => 'coop-tilleuls--ea-extensions--refresh-choices-on-related-field-change',
        'data-coop-tilleuls--ea-extensions--refresh-choices-on-related-field-change-listen-value' => json_encode(['authorA', 'authorB']),
    ])
    ->setHtmlAttribute('data-coop-tilleuls--ea-extensions--refresh-choices-on-related-field-change-target', 'field')
```
