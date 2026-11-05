<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Same as {@see Kernel}, with "notifications.crud_controller" pointing at a host app's own CRUD
 * ({@see FixtureNotificationCrudController}) instead of the bundle's.
 */
final class CustomNotificationCrudKernel extends Kernel
{
    public function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        parent::configureContainer($container, $loader, $builder);

        $container->extension('coop_tilleuls_ea_extensions', [
            'notifications' => [
                'crud_controller' => FixtureNotificationCrudController::class,
            ],
        ]);

        $container->services()->defaults()->autowire()->autoconfigure()
            ->set(FixtureNotificationCrudController::class)->public();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/cache-custom-notification-crud/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/log-custom-notification-crud';
    }
}
