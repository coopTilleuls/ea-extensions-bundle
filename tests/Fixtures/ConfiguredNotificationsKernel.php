<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Same as {@see Kernel}, with labelled notification types and a display timezone.
 */
final class ConfiguredNotificationsKernel extends Kernel
{
    public function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        parent::configureContainer($container, $loader, $builder);

        $container->extension('coop_tilleuls_ea_extensions', [
            'notifications' => [
                'types' => [
                    'validation' => 'Validation',
                    'information' => 'Information',
                ],
                'timezone' => 'Europe/Paris',
            ],
        ]);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/cache-configured-notifications/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/log-configured-notifications';
    }
}
