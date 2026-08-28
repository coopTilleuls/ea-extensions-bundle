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

## Features

Each feature has its own setup/usage doc in [`docs/`](docs/):

- [Menu overrides](docs/menu-overrides.md) — infinite-depth submenus and correct active-item
  highlighting when two menu items share the same destination.

## Development

```bash
composer install
composer test      # PHPUnit
composer phpstan    # PHPStan
composer cs          # PHP-CS-Fixer (dry-run)
composer rector      # Rector (dry-run)
```
