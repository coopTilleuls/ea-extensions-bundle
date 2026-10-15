<?php

declare(strict_types=1);

use CoopTilleuls\Bundle\EAExtensionsBundle\BackToTop\EasyAdmin\BackToTopListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services->load('CoopTilleuls\\Bundle\\EAExtensionsBundle\\BackToTop\\', __DIR__.'/../src/BackToTop/');

    $services->set(BackToTopListener::class)
        ->arg('$threshold', '%coop_tilleuls_ea_extensions.back_to_top.threshold%')
    ;
};
