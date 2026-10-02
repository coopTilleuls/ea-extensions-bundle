# `select-all-options`

Selects every available option on a TomSelect-enhanced multi-select field, triggered from a
button you render next to it.

See [JS controllers](README.md) for the one-time consumer app setup.

Unlike every other controller in this bundle, this one has no targets or values — it's a plain
Stimulus action. `data-controller` just needs to be on any ancestor of the button (its own field
row is enough, no need to scope it to the whole form).

For a locally-populated field (fixed choices — including a plain `ChoiceField::autocomplete()`,
which is still TomSelect-enhanced but has no remote endpoint), every already-known option is
selected directly. For a field with a real remote [autocomplete endpoint](https://symfony.com/bundles/EasyAdminBundle/current/fields.html)
(an `AssociationField`/`ChoiceField` with `data-ea-autocomplete-endpoint-url` set — that's what
this controller actually checks for, not just `->autocomplete()`), an empty-query fetch is
triggered first so options actually get selected from what that fetch returns — **if the
endpoint paginates its results, only that first batch gets selected, not necessarily every
entity that could ever match**. This is a real limitation of "select all" against a remote,
paginated source, not something this controller can resolve on its own.

## Markup

The button must be a sibling of the `<select>` (i.e. share the same parent element) and carry
`data-action="click-><identifier>#selectAll"`:

```html
<div data-controller="coop-tilleuls--ea-extensions--select-all-options">
    <select name="Book[genres][]" multiple>
        <!-- ... -->
    </select>
    <button type="button" data-action="click->coop-tilleuls--ea-extensions--select-all-options#selectAll">
        Select all
    </button>
</div>
```

EasyAdmin doesn't render such a button on its own — you need a small custom form theme that adds
it. This bundle doesn't ship one, since exactly how you'd want that button styled/placed is
app-specific, but here's a small, narrowly-scoped one that works: `choice_widget_collapsed` is
the Symfony form-theme block that renders a `ChoiceField`'s bare `<select>` tag (whether or not
it uses `->autocomplete()`) with no wrapper element around it, so appending markup right after
`{{ parent() }}` lands it as a true sibling of the `<select>` — no need to copy EasyAdmin's whole
field-row template. Extend `@EasyAdmin/crud/form_theme.html.twig` directly as shown below, or
your own app's existing form theme override if it has one (so you don't lose it):

```twig
{# templates/admin/select_all_options_form_theme.html.twig #}
{% extends '@EasyAdmin/crud/form_theme.html.twig' %}

{% block choice_widget_collapsed %}
    {{ parent() }}
    {% if attr['data-select-all-options-button'] ?? false %}
        <button type="button" class="select-all"
                data-action="click->coop-tilleuls--ea-extensions--select-all-options#selectAll">
            Select all
        </button>
    {% endif %}
{% endblock %}
```

```php
// in configureCrud()
Crud::new()->addFormTheme('admin/select_all_options_form_theme.html.twig');
```

```php
// in configureFields(), on the field that needs the button
ChoiceField::new('genres')
    ->allowMultipleChoices()
    ->autocomplete()
    ->setFormTypeOption('row_attr', ['data-controller' => 'coop-tilleuls--ea-extensions--select-all-options'])
    ->setHtmlAttribute('data-select-all-options-button', 'true')
```

The `data-select-all-options-button` attribute is what keeps this opt-in per field — without it,
every `ChoiceField` on forms using this theme would get the button.

## Pairs well with `refresh-values-on-related-field-change` / `refresh-choices-on-related-field-change`

Those two controllers already look for a sibling `.select-all`-classed button and
enable/disable it together with their own target field (see their own docs, "Select-all
button" section) — give your button that class and both behaviors compose for free: the button
is only enabled once the field itself is, and clicking it selects everything currently on offer.
