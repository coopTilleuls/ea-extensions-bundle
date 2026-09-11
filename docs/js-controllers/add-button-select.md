# `add-button-select`

Adds an "Add" button next to every TomSelect-enhanced multi-select field in its scope, that
opens the field's dropdown when clicked. Useful for a multi-select whose own input disappears
once one or more items are already selected, leaving no obvious way to open it again.

See [JS controllers](README.md) for the one-time consumer app setup.

No targets or values — just put `data-controller` on an element that wraps whichever
multi-select field(s) should get the button. A single field's own row is enough; there's no
need to scope this to the whole form (unlike `display-field-on-boolean-field-change`).

The button is appended into the field's `.form-widget` container (the wrapper EasyAdmin already
renders around every field's widget — nothing to add for that), and is disabled/enabled
automatically together with the select itself.

## Example

```php
ChoiceField::new('genres')
    ->allowMultipleChoices()
    ->autocomplete()
    ->setFormTypeOption('row_attr', [
        'data-controller' => 'coop-tilleuls--ea-extensions--add-button-select',
    ]);
```

No further wiring needed — every multi-select found inside that `row_attr` wrapper (here, just
the one field) gets its own "Add" button automatically.
