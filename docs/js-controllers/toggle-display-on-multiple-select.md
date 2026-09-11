# `toggle-display-on-multiple-select`

Shows a field only once another multi-select field has at least a minimum number of items
selected — hides it otherwise. Useful for a field that's only meaningful once several items are
picked, e.g. an "and/or" operator between two or more selected entities.

See [JS controllers](README.md) for the one-time consumer app setup.

The listened field must be a TomSelect-enhanced multi-select [autocomplete field](https://symfony.com/bundles/EasyAdminBundle/current/fields.html)
(any field with `->autocomplete()` and `->allowMultipleChoices()`, or equivalent, called on it)
— the controller reads its current selection count through TomSelect.

## Targets

| Target  | Element                                |
|---------|------------------------------------------|
| `field` | the field to show or hide |

## Values

| Value      | Type   | Required | Default | Description                                                            |
|------------|--------|----------|----------|--------------------------------------------------------------------------|
| `listen`   | String | yes      | —        | Name of the related multi-select field to watch.                       |
| `minCount` | Number | no       | `2`      | Minimum number of selected items on the listened field required to show the target. |

`listen` is matched against the DOM by name: `foo` matches `form[foo][]` or
`form[foo][autocomplete][]`.

## Example

```html
<div data-controller="coop-tilleuls--ea-extensions--toggle-display-on-multiple-select"
     data-coop-tilleuls--ea-extensions--toggle-display-on-multiple-select-listen-value="recipients">
    <select name="Broadcast[operator]"
            data-coop-tilleuls--ea-extensions--toggle-display-on-multiple-select-target="field">
        <!-- ... -->
    </select>

    <select name="Broadcast[recipients][]" multiple><!-- ... --></select>
</div>
```

Here, `operator` (an "and"/"or" choice) only shows once 2 or more `recipients` are selected —
with a single recipient (or none), there's nothing to combine, so the field stays hidden.

Equivalent EasyAdmin field configuration:

```php
ChoiceField::new('operator')
    ->setFormTypeOption('row_attr', [
        'data-controller' => 'coop-tilleuls--ea-extensions--toggle-display-on-multiple-select',
        'data-coop-tilleuls--ea-extensions--toggle-display-on-multiple-select-listen-value' => 'recipients',
    ])
    ->setHtmlAttribute('data-coop-tilleuls--ea-extensions--toggle-display-on-multiple-select-target', 'field')
```
