<?php

declare(strict_types=1);

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Controller\ColumnPreferenceController;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage\ColumnPreferenceStorageInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage\DoctrineColumnPreferenceStorage;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services->load('CoopTilleuls\\Bundle\\EAExtensionsBundle\\CustomizableColumns\\', __DIR__.'/../src/CustomizableColumns/')
        ->exclude(__DIR__.'/../src/CustomizableColumns/{Entity,Controller,CustomizableColumnsConfigurationInterface.php}')
    ;

    $services->set(ColumnPreferenceController::class)
        ->tag('controller.service_arguments')
        ->public()
    ;

    $services->alias(ColumnPreferenceStorageInterface::class, DoctrineColumnPreferenceStorage::class);
};
