# Customizable index columns

Lets users choose and reorder which fields show on any CRUD's index page, with a saved-per-user preference. Works on any `AbstractCrudController` — no trait, no base class, no per-controller code required. Enabled by default; disable via:

```yaml
# config/packages/coop_tilleuls_ea_extensions.yaml
coop_tilleuls_ea_extensions:
    customizable_columns:
        enabled: false
```

## Consumer app setup (one-time)

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
3. Add an importmap entry for the bundle's JS entrypoint (same pattern as `cgt/theme-bundle`'s `theme` entry — the bundle ships a plain `public/` directory, published via `bin/console assets:install`, with an entrypoint that registers its own Stimulus controllers). `assets:install` derives the public folder name from the bundle's short class name minus the `Bundle` suffix, lowercased — `EAExtensionsBundle` → `bundles/eaextensions/` (run `bin/console assets:install` first and check `public/bundles/` if unsure):
   ```php
   // importmap.php
   'ea-extensions' => [
       'path' => 'bundles/eaextensions/ea-extensions.js',
       'entrypoint' => true,
   ],
   ```
   then load it on every admin page by adding `->addAssetMapperEntry('ea-extensions')` to your dashboard controller's `configureAssets()`:
   ```php
   public function configureAssets(): Assets
   {
       return parent::configureAssets()->addAssetMapperEntry('ea-extensions');
   }
   ```
4. By default, preferences are stored one row per owner (the current Security user's identifier) in a bundle-owned `ea_extensions_column_preference` table — a single JSON column mapping each CrudController FQCN to its saved column list. Run `doctrine:migrations:diff` after installing to generate that table's migration. To key preferences differently (e.g. per active "responsibility" instead of per user), override the `CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage\ColumnPreferenceStorageInterface` service alias with your own implementation — the default is `SecurityUserColumnPreferenceStorage`.

## Optional per-controller fine-tuning

Implement `CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\CustomizableColumnsConfigurationInterface` on a `CrudController` to declare locked (always-visible) columns and a non-default initial column set:

```php
final class BookCrudController extends AbstractCrudController implements CustomizableColumnsConfigurationInterface
{
    public static function getLockedColumns(): array { return ['title']; }
    public static function getDefaultColumns(): array { return ['title', 'author']; }
}
```

## Applying vs. saving

Matches cgt-cogitiel's UX: choosing columns in the modal and clicking "Appliquer" is **transient** by default — it only changes the current page view, nothing is saved. To make a selection the new default, the user must check the "Enregistrer comme paramètres par défaut" switch in the modal before applying. "Réinitialiser" clears every checkbox except that switch, so it doesn't persist either unless the switch was already checked.
