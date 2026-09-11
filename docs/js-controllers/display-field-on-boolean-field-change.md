# `display-field-on-boolean-field-change`

Shows or hides a field's container depending on a boolean (checkbox/switch) field.

See [JS controllers](README.md) for the one-time consumer app setup.

Unlike the other controllers in this bundle, this one doesn't look up the listened field by
name anywhere in the document — it relies on Stimulus targets, so **both fields must live
inside the same `data-controller` scope**. In practice that's usually the whole `<form>`, set
via EasyAdmin's own `Crud::setFormOptions()` (no bundle-specific wiring needed for that part).

## Targets

| Target         | Element                                                                                  |
|----------------|---------------------------------------------------------------------------------------------|
| `field`        | the element whose container is shown or hidden                                          |
| `fieldToListen`| the boolean (checkbox) field to listen to — also needs its own `data-action` (see below) |

## Values

| Value       | Type    | Required | Default        | Description                                                                          |
|-------------|---------|----------|-----------------|------------------------------------------------------------------------------------------|
| `invert`    | Boolean | no       | `false`         | Hide (instead of show) the target when the boolean field is checked.                 |
| `container` | String  | no       | `.form-group`   | CSS selector, resolved from the `field` target via `closest()`, of the actual element to show/hide. |

The `fieldToListen` target must also carry a plain Stimulus `data-action` attribute yourself:
`data-action="change-><identifier>#toggleDisplay"` — this controller doesn't wire that
listener for you, since the action needs to name the controller's own identifier.

## Example

```php
// in configureCrud()
Crud::new()
    ->setFormOptions(['attr' => [
        'data-controller' => 'coop-tilleuls--ea-extensions--display-field-on-boolean-field-change',
    ]]);
```

```html
<input type="checkbox"
       name="Employee[isRemote]"
       data-coop-tilleuls--ea-extensions--display-field-on-boolean-field-change-target="fieldToListen"
       data-action="change->coop-tilleuls--ea-extensions--display-field-on-boolean-field-change#toggleDisplay">

<div class="form-group">
    <input name="Employee[homeAddress]"
           data-coop-tilleuls--ea-extensions--display-field-on-boolean-field-change-target="field">
</div>
```

Here, `homeAddress`'s `.form-group` is only shown while `isRemote` is checked.

Equivalent EasyAdmin field configuration:

```php
BooleanField::new('isRemote')
    ->renderAsSwitch()
    ->setHtmlAttribute('data-coop-tilleuls--ea-extensions--display-field-on-boolean-field-change-target', 'fieldToListen')
    ->setHtmlAttribute('data-action', 'change->coop-tilleuls--ea-extensions--display-field-on-boolean-field-change#toggleDisplay'),
TextField::new('homeAddress')
    ->setHtmlAttribute('data-coop-tilleuls--ea-extensions--display-field-on-boolean-field-change-target', 'field'),
```

### Inverted mode with a custom container

`invert` and `container` are values, read from the same element as `data-controller` — since
that's normally the whole form here, they apply to every `field`/`fieldToListen` pair on that
form (fine as long as there's only one pair per form, which covers every case seen so far):

```php
Crud::new()
    ->setFormOptions(['attr' => [
        'data-controller' => 'coop-tilleuls--ea-extensions--display-field-on-boolean-field-change',
        'data-coop-tilleuls--ea-extensions--display-field-on-boolean-field-change-invert-value' => 'true',
        'data-coop-tilleuls--ea-extensions--display-field-on-boolean-field-change-container-value' => '.form-fieldset',
    ]]);
```

Here, the whole `.form-fieldset` ancestor is hidden while `isRemote` is checked (and shown while
unchecked), instead of the default `.form-group`.
