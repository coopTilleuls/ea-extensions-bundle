# EAExtensionsBundle

EasyAdmin extensions bundle for Les-Tilleuls.coop / coopTilleuls projects.

## Installation

This bundle is not published on Packagist. Add the VCS repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/coopTilleuls/ea-extensions-bundle"
        }
    ]
}
```

Then require it. This bundle has no tagged release yet (branches only), so with the
default `minimum-stability: stable`, a plain `composer require` fails with:

```
Could not find a version of package coop-tilleuls/ea-extensions-bundle matching your
minimum-stability (stable). Require it with an explicit version constraint allowing
its desired stability.
```

Pin an explicit dev-branch constraint instead:

```bash
composer require coop-tilleuls/ea-extensions-bundle:dev-main
```

If Symfony Flex doesn't register it automatically, add it to `config/bundles.php`:

```php
CoopTilleuls\Bundle\EAExtensionsBundle\EAExtensionsBundle::class => ['all' => true],
```

Requires **PHP >=8.4** (`easycorp/easyadmin-bundle` 5.5's UI only renders correctly with `symfony/ux-twig-component` ^3.0, which itself requires PHP 8.4 — see the `bundle-ci-scaffolding` skill for details).

## Setup (one-time, shared by every feature)

### Twig

EasyAdmin 5.5's own Twig components need these two bundles and an anonymous component
directory. Register them in `config/bundles.php` if not already present:

```php
Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
```

```yaml
# config/packages/twig_component.yaml
twig_component:
    anonymous_template_directory: 'components'
```

### JavaScript

Every feature or controller that has a front-end part goes through a single entrypoint. It needs
`symfony/stimulus-bundle` (AssetMapper + Stimulus) in the app.

1. Declare the entrypoint in the importmap:
   ```php
   // importmap.php
   'ea-extensions' => [
       'path' => 'bundles/eaextensions/ea-extensions.js',
       'entrypoint' => true,
   ],
   ```
2. Load it on every admin page, in **each** dashboard controller:
   ```php
   public function configureAssets(): Assets
   {
       return parent::configureAssets()->addAssetMapperEntry('ea-extensions');
   }
   ```
   An `'entrypoint' => true` importmap entry is never executed on its own: without this call
   nothing from the bundle runs, silently.

Controllers are registered lazily: a page only downloads the controllers (and their stylesheets)
whose `data-controller` it actually contains, so loading the entrypoint everywhere costs nothing
for disabled features.

## Features

Every feature is **disabled by default** and enabled under `coop_tilleuls_ea_extensions` in
`config/packages/coop_tilleuls_ea_extensions.yaml`. Each doc in [`docs/`](docs/) covers its
configuration and any setup beyond the shared one above:

| Feature | What it does | Uses the JS entrypoint | Extra setup |
|---|---|---|---|
| [Menu overrides](docs/menu-overrides.md) | infinite-depth submenus, correct active item when two items share a destination | no | include a template in your menu template |
| [Customizable index columns](docs/customizable-columns.md) | per-user choice and ordering of index columns | yes | routes, migration |
| [CrudFields](docs/crud-fields.md) | embed a CrudController's index listing in another page | yes | one call in the dashboard's `configureCrud()` |
| [Configurable pagination](docs/pagination.md) | page-size selector on index pages | yes | two template overrides |
| [CRUD index export](docs/crud-export.md) | CSV/XLSX export of index pages, sync or async | no | extra packages, routes, storage, Messenger routing |
| [Index-only search](docs/index-only-search.md) | top-bar search box on index pages only | no | none |
| [Tab navigation](docs/tab-navigation.md) | "Previous tab" / "Next tab" buttons on tabbed forms | yes | none |
| [Back to top](docs/back-to-top.md) | floating "Back to top" button | yes | none |
| [Notifications](docs/notifications.md) | notification entity, CRUD, unread badge, optional real-time popover (Mercure) | yes | routes, menu item, recipient mapping, migration, layout include |

## JS controllers

Standalone Stimulus controllers, reusable directly in your own form templates, needing only the
[JavaScript setup](#javascript). The full list is in
[`docs/js-controllers/`](docs/js-controllers/README.md).

## Development

```bash
composer install
composer test      # PHPUnit
composer phpstan    # PHPStan
composer cs          # PHP-CS-Fixer (dry-run)
composer rector      # Rector (dry-run)
```
