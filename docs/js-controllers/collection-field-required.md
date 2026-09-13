# `collection-field-required`

Keeps a `CollectionField` from ever being emptied out: auto-adds a first item the moment the
collection is empty (including right on page load), and hides every "delete item" button while
exactly one item remains.

See [JS controllers](README.md) for the one-time consumer app setup.

This isn't a form-level "required" constraint — `CollectionField` has no such option, and this
controller doesn't add one (an empty collection still submits and validates like any other). It's
a UX guarantee only: the collection is never visibly empty, and the user can't remove the last
item from it either.

It reads EasyAdmin's own collection-field markup (`.ea-form-collection-items`,
`.field-collection-add-button`, `.field-collection-delete-button`, `.collection-empty` — see
`vendor/easycorp/easyadmin-bundle/templates/crud/form_theme.html.twig`) as descendants of
`this.element`, so `data-controller` just needs to land on something that wraps the whole field —
its `row_attr` form option, same as most other controllers in this collection.

## Values

None — this controller has no configuration; its behavior is fixed.

## Example

```php
CollectionField::new('postalAddresses')
    ->setEntryType(PostalAddressType::class)
    ->allowAdd()
    ->allowDelete()
    ->setFormTypeOption('row_attr', [
        'data-controller' => 'coop-tilleuls--ea-extensions--collection-field-required',
    ]);
```

No template change needed: unlike `select-all-options` (which adds a button EasyAdmin has no
native equivalent for), this controller only observes and clicks buttons EasyAdmin's own
`CollectionField` already renders.
