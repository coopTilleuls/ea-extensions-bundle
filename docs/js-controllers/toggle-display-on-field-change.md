# `toggle-display-on-field-change`

Keeps a form field's visibility in sync with one related field on the same form: whenever the
listened field's value changes, the target field (and, optionally, extra elements around it) is
shown or hidden depending on whether the new value is allowed or forbidden by a whitelist or
blacklist.

See [JS controllers](README.md) for the one-time consumer app setup.

The listened field must be a TomSelect-enhanced [autocomplete field](https://symfony.com/bundles/EasyAdminBundle/current/fields.html)
(any field with `->autocomplete()` called on it) — the controller reads its current selection
through TomSelect. The target field itself doesn't have to be an autocomplete field: it can be
any input, and falls back to its plain `value` when it has no TomSelect instance.

## Targets

| Target  | Element                                |
|---------|------------------------------------------|
| `field` | the field to show or hide |

## Values

| Value             | Type    | Required | Default | Description                                                                                                            |
|-------------------|---------|----------|----------|--------------------------------------------------------------------------------------------------------------------------|
| `listen`          | String  | yes      | —        | Name of the related field to listen to.                                                                                |
| `whitelist`       | Array   | one of `whitelist`/`blacklist` required | — | Values of the listened field that keep the target displayed.                                              |
| `blacklist`       | Array   | one of `whitelist`/`blacklist` required | — | Values of the listened field that hide the target.                                                        |
| `defaultDisplay`  | Boolean | no       | `false`  | Display the target by default and hide it only when a `blacklist` value is selected, instead of the reverse.          |
| `defaultValue`    | String  | no       | —        | Value restored on the target once it becomes visible again and nothing was previously stored for it.                  |
| `selectorToHide`  | String  | no       | —        | Extra CSS selector for elements to show/hide together with the target.                                                |
| `keepValue`       | Boolean | no       | `false`  | Only toggle visibility, never clear or restore the target's value — use for required fields that must always keep one.|

`listen` is matched against the DOM by name: `foo` matches `form[foo]`, `form[foo][]`,
`form[foo][autocomplete]` or `form[foo][autocomplete][]`.

## Example

```html
<div data-controller="coop-tilleuls--ea-extensions--toggle-display-on-field-change"
     data-coop-tilleuls--ea-extensions--toggle-display-on-field-change-listen-value="status"
     data-coop-tilleuls--ea-extensions--toggle-display-on-field-change-whitelist-value='["active", ""]'>
    <select name="Employee[contractType]"
            data-coop-tilleuls--ea-extensions--toggle-display-on-field-change-target="field">
        <!-- ... -->
    </select>

    <select name="Employee[status]"><!-- ... --></select>
</div>
```

Here, `contractType` is only shown while `status` is `active` (or still empty). When `status`
changes to any other value, `contractType`'s current selection is stored, the field is cleared
and hidden; when `status` goes back to an allowed value, the stored selection is restored.

Equivalent EasyAdmin field configuration:

```php
AssociationField::new('contractType')
    ->setFormTypeOption('row_attr', [
        'data-controller' => 'coop-tilleuls--ea-extensions--toggle-display-on-field-change',
        'data-coop-tilleuls--ea-extensions--toggle-display-on-field-change-listen-value' => 'status',
        'data-coop-tilleuls--ea-extensions--toggle-display-on-field-change-whitelist-value' => json_encode(['active', '']),
    ])
    ->setHtmlAttribute('data-coop-tilleuls--ea-extensions--toggle-display-on-field-change-target', 'field')
```

## First-load behavior on an edit page

On the very first toggle triggered while the page is an *edit* page (its URL contains `/edit`),
the target's value is left untouched even if the toggle would otherwise clear it — the field's
already-persisted value is trusted as-is. This only applies to that first toggle; any later
change while the page stays open follows the normal clear/restore behavior described above. On a
*new*-entity page, the normal behavior applies from the very first toggle.
