<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mercure\HubInterface;

/**
 * Same as {@see Kernel}, with "notifications.mercure.enabled" turned on and a
 * {@see RecordingMercureHub} standing in for a real Mercure hub — used to exercise the
 * Mercure-enabled wiring path without requiring a real Mercure hub connection.
 */
final class MercureKernel extends Kernel
{
    public function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        parent::configureContainer($container, $loader, $builder);

        $container->extension('coop_tilleuls_ea_extensions', [
            'notifications' => [
                'mercure' => [
                    'enabled' => true,
                ],
            ],
        ]);

        $services = $container->services();
        $services->set(RecordingMercureHub::class)->public();
        $services->alias(HubInterface::class, RecordingMercureHub::class)->public();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/cache-mercure/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/log-mercure';
    }
}
