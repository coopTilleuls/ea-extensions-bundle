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

Then require it:

```bash
composer require coop-tilleuls/ea-extensions-bundle
```

If Symfony Flex doesn't register it automatically, add it to `config/bundles.php`:

```php
CoopTilleuls\Bundle\EAExtensionsBundle\EAExtensionsBundle::class => ['all' => true],
```

Requires **PHP >=8.4** (`easycorp/easyadmin-bundle` 5.5's UI only renders correctly with `symfony/ux-twig-component` ^3.0, which itself requires PHP 8.4 — see the `bundle-ci-scaffolding` skill for details).

## Features

Each feature has its own setup/usage doc in [`docs/`](docs/):

- [Customizable index columns](docs/customizable-columns.md) — per-user choice and ordering of visible fields on any CRUD's index page.

## Development

```bash
composer install
composer test      # PHPUnit
composer phpstan    # PHPStan
composer cs          # PHP-CS-Fixer (dry-run)
composer rector      # Rector (dry-run)
```
