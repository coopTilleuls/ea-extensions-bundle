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

## Features

Each feature has its own setup/usage doc in [`docs/`](docs/):

- [Menu overrides](docs/menu-overrides.md) — infinite-depth submenus and correct active-item
  highlighting when two menu items share the same destination.
- [Customizable index columns](docs/customizable-columns.md) — per-user choice and ordering of visible fields on any CRUD's index page.
- [CrudFields](docs/crud-fields.md) — embed one CrudController's index listing inside another entity's page (e.g. a detail page tab).
- [Configurable pagination](docs/pagination.md) — lets users pick how many rows show per page on any CRUD's index page.
- [CRUD index export](docs/crud-export.md) — CSV/XLSX export of any CRUD's index page, sync or async.
- [Index-only search](docs/index-only-search.md) — shows the top-bar search box on index pages only, not on detail/edit/new pages.
- [Tab navigation](docs/tab-navigation.md) — "Previous tab" / "Next tab" buttons at the bottom of each tab of any tabbed form.
- [Back to top](docs/back-to-top.md) — floating "Back to top" button on every admin page, shown once scrolled.

## JS controllers

Standalone Stimulus controllers, reusable directly in your own form templates — see
[`docs/js-controllers/`](docs/js-controllers/README.md) for the full list and setup.

## Development

```bash
composer install
composer test      # PHPUnit
composer phpstan    # PHPStan
composer cs          # PHP-CS-Fixer (dry-run)
composer rector      # Rector (dry-run)
```
