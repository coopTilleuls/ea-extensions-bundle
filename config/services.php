<?php

declare(strict_types=1);

use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\EasyAdmin\RecursiveMenuItemMatcher;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemMatcherInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
    ;

    $services->load(
        'CoopTilleuls\\Bundle\\EAExtensionsBundle\\MenuOverrides\\',
        __DIR__.'/../src/MenuOverrides/'
    )->exclude(__DIR__.'/../src/MenuOverrides/MenuItemTag.php');

    $services->alias(MenuItemMatcherInterface::class, RecursiveMenuItemMatcher::class);
};
