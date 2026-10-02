# `tomselect-optgroups`

Groups a TomSelect-enhanced field's options under named optgroups.

See [JS controllers](README.md) for the one-time consumer app setup.

This controller only *declares the groups* and tells TomSelect which item property to group
by — it doesn't invent grouping data out of nothing. Each option/result still has to carry a
matching `optgroup` value:

- for a remote [autocomplete field](https://symfony.com/bundles/EasyAdminBundle/current/fields.html)
  (`AssociationField`/`ChoiceField` with `->autocomplete()` and a real endpoint), each JSON result
  needs an `optgroup` key alongside `entityId`/`entityAsString` — add it in whatever builds that
  JSON response (e.g. the entity's serialization/`formatValue()`).
- for a local, fixed-choice field, each `<option>` needs a `data-optgroup="<group value>"`
  attribute — TomSelect's own DOM import turns a `data-*` attribute on an `<option>` into a
  matching item property automatically, so this doesn't need any extra JS of its own; get
  `choice_attr` (Symfony's `ChoiceType` option) to render it.

Put `data-controller` directly on the field itself (not a wrapper `row_attr`) — this controller
reads and patches the field's own TomSelect instance via `this.element`, so it needs to *be* that
element, not an ancestor of it.

## Values

| Value       | Type  | Required | Description                                                                 |
|-------------|-------|----------|--------------------------------------------------------------------------------|
| `optgroups` | Array | yes      | The groups to declare, each `{ value: string, label: string }` (TomSelect's own optgroup shape). |

## Example

```php
AssociationField::new('author')
    ->autocomplete()
    ->setHtmlAttribute('data-controller', 'coop-tilleuls--ea-extensions--tomselect-optgroups')
    ->setHtmlAttribute(
        'data-coop-tilleuls--ea-extensions--tomselect-optgroups-optgroups-value',
        json_encode([
            ['value' => 'sci-fi', 'label' => 'Science-fiction authors'],
            ['value' => 'fantasy', 'label' => 'Fantasy authors'],
        ]),
    );
```

with the autocomplete endpoint's own JSON results each including an `"optgroup": "sci-fi"` (or
`"fantasy"`) key alongside the usual `entityId`/`entityAsString`.
