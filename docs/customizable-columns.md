# Customizable index columns

Lets users choose and reorder which fields show on any CRUD's index page, with a saved-per-user preference. Works on any `AbstractCrudController` — no trait, no base class, no per-controller code required. Enabled by default; disable via:

```yaml
# config/packages/coop_tilleuls_ea_extensions.yaml
coop_tilleuls_ea_extensions:
    customizable_columns:
        enabled: false
```

## Consumer app setup (one-time)

1. Import the bundle's routes in `config/routes.php` (or `.yaml`):
   ```php
   $routes->import('vendor/coop-tilleuls/ea-extensions-bundle/src/CustomizableColumns/Controller/', 'attribute');
   ```
2. Register these bundles in `config/bundles.php` if not already present (required for `easycorp/easyadmin-bundle` 5.5's own Twig components to render at all — `TwigExtraBundle` provides the `html_classes` Twig function EasyAdmin's templates use, `TwigComponentBundle` backs its `<twig:ea:...>` components):
   ```php
   Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
   Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
   ```
   and configure `twig_component.anonymous_template_directory` (required by `symfony/ux-twig-component` ^3.0, no default):
   ```yaml
   # config/packages/twig_component.yaml
   twig_component:
       anonymous_template_directory: 'components'
   ```
3. Add the bundle's Stimulus controllers to `assets/controllers.json` / an `importmap` entry pointing at `vendor/coop-tilleuls/ea-extensions-bundle/assets/customizable_columns/*.js` (or your app's usual AssetMapper path config).
4. By default, preferences are stored per-user in a bundle-owned `ea_extensions_column_preference` table — run `doctrine:migrations:diff` after installing to generate that table's migration. To key preferences differently (e.g. per active "responsibility" instead of per user), override the `CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage\ColumnPreferenceStorageInterface` service alias with your own implementation.

## Optional per-controller fine-tuning

Implement `CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\CustomizableColumnsConfigurationInterface` on a `CrudController` to declare locked (always-visible) columns and a non-default initial column set:

```php
final class BookCrudController extends AbstractCrudController implements CustomizableColumnsConfigurationInterface
{
    public static function getLockedColumns(): array { return ['title']; }
    public static function getDefaultColumns(): array { return ['title', 'author']; }
}
```

## How it works

- `CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\EasyAdmin\CustomizableColumnsActionsExtension` (a tagged `ActionsExtensionInterface` service) adds the "Personnaliser les colonnes" global action on `Crud::PAGE_INDEX` for any CrudController — no per-controller wiring.
- `CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\EasyAdmin\CustomizableColumnsFieldsSubscriber` listens to EasyAdmin's `AfterCrudActionEvent` and filters/reorders each row's already-resolved `FieldCollection` according to the resolved column selection (query override → saved preference → controller defaults → natural order).
- `ColumnPreferenceStorageInterface` is the pluggable persistence contract; `DoctrineColumnPreferenceStorage` is the default implementation.
- `ColumnPreferenceController` serves the picker modal (`GET /_ea-extensions/customizable-columns/render`) and the save endpoint (`POST /_ea-extensions/customizable-columns/save`), both plain Symfony routes — not EasyAdmin `#[AdminRoute]`s, so no per-controller route registration is needed.
