# Customizable index columns

Lets users choose and reorder which fields show on any CRUD's index page, with a saved-per-user preference. Works on any `AbstractCrudController` — no trait, no base class, no per-controller code required. Disabled by default; enable via:

```yaml
# config/packages/coop_tilleuls_ea_extensions.yaml
coop_tilleuls_ea_extensions:
    customizable_columns:
        enabled: true
```

## Consumer app setup (one-time)

On top of the [shared setup](../README.md#setup-one-time-shared-by-every-feature) (Twig and JavaScript):

1. Import the bundle's routes, e.g. in `config/routes/ea_extensions.yaml` (Symfony Flex's
   default format). Use the `@EAExtensionsBundle` shortcut rather than a `vendor/`-relative
   path — it resolves via the bundle's own path regardless of where it's actually installed
   (vendor, path repository, submodule...):
   ```yaml
   ea_extensions_customizable_columns:
       resource: '@EAExtensionsBundle/src/CustomizableColumns/Controller/'
       type: attribute
   ```
   or in PHP, in `config/routes.php`:
   ```php
   $routes->import('@EAExtensionsBundle/src/CustomizableColumns/Controller/', 'attribute');
   ```
2. By default, preferences are stored one row per owner (the current Security user's identifier) in a bundle-owned `ea_extensions_column_preference` table — a single JSON column mapping each CrudController FQCN to its saved column list. Run `doctrine:migrations:diff` after installing to generate that table's migration. To key preferences differently (e.g. per active "responsibility" instead of per user), implement `CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage\ColumnPreferenceStorageInterface` and point `storage_service_id` at it — the default is `SecurityUserColumnPreferenceStorage`:
   ```yaml
   coop_tilleuls_ea_extensions:
       customizable_columns:
           enabled: true
           storage_service_id: App\ColumnPreference\MyColumnPreferenceStorage
   ```
   The bundle then leaves its `ColumnPreference` entity unmapped, so no `ea_extensions_column_preference` table shows up in `doctrine:migrations:diff` or in fixture purges.

## Column names

A column is identified by its field's property: that name goes in the `eaColumns` query parameter, in saved preferences and in `getLockedColumns()` / `getDefaultColumns()`. When the property is not a usable identifier, set the `ColumnName::OPTION` custom option on the field:

```php
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnName;

yield CollectionField::new('emails')->setCustomOption(ColumnName::OPTION, 'email');
```

Typical cases:

- a virtual field whose property is generated (e.g. suffixed with a ULID to stay unique): the property changes on every request, so without a stable name the column can never be matched again;
- an app that already refers to its columns under other names (saved preferences, links), which then keep working.

## Optional per-controller fine-tuning

Implement `CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\CustomizableColumnsConfigurationInterface` on a `CrudController` to declare locked (always-visible) columns and a non-default initial column set:

```php
final class BookCrudController extends AbstractCrudController implements CustomizableColumnsConfigurationInterface
{
    public function getLockedColumns(): array { return ['title']; }
    public function getDefaultColumns(): array { return ['title', 'author']; }
}
```

Both methods are called on the controller instance, so they can depend on the current user:

```php
public function getDefaultColumns(): array
{
    return $this->isGranted('ROLE_EDITOR') ? ['title', 'author', 'status'] : ['title', 'author'];
}
```

## Skipping hidden columns

EasyAdmin formats every field returned by `configureFields()` for every row, and the bundle only drops the hidden columns afterwards. A hidden column that reads a relation (an `AssociationField`, a collection, a value computed from related entities) still loads that relation for each row.

To skip that work, filter the index fields yourself with `CustomizableColumnsFieldsFilter`. It applies the same selection as the bundle (query parameter, saved preference, default and locked columns), and the column picker turns it off while it lists the available columns:

```php
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\CustomizableColumnsFieldsFilter;

public function __construct(
    private readonly CustomizableColumnsFieldsFilter $fieldsFilter,
) {
}

public function configureFields(string $pageName): iterable
{
    $fields = [/* every candidate column */];

    return Crud::PAGE_INDEX === $pageName ? $this->fieldsFilter->filter($fields, $this) : $fields;
}
```

The filter only acts on controllers implementing `CustomizableColumnsConfigurationInterface` and returns the fields untouched otherwise.

## Applying vs. saving

Choosing columns in the modal and clicking "Appliquer" is **transient** by default — it only changes the current page view, nothing is saved. To make a selection the new default, the user must check the "Enregistrer comme paramètres par défaut" switch in the modal before applying. "Réinitialiser" clears every checkbox except that switch, so it doesn't persist either unless the switch was already checked.
