<?php

declare(strict_types=1);

use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\EasyAdmin\RecursiveMenuFactory;
use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\EasyAdmin\RecursiveMenuItemMatcher;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemMatcherInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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

    // EasyAdmin registers this service by concrete class id, without a matching autowiring
    // alias for its own type — its own MenuFactory only gets it because EasyAdmin wires it by
    // hand rather than autowiring.
    $services->get(RecursiveMenuFactory::class)
        ->arg('$logoutUrlGenerator', service('security.logout_url_generator'));

    $services->alias(MenuItemMatcherInterface::class, RecursiveMenuItemMatcher::class);
};
