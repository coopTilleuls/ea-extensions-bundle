<?php

declare(strict_types=1);

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher\MercureNotificationPublisher;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher\NotificationPublisherInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * Only imported by {@see \CoopTilleuls\Bundle\EAExtensionsBundle\EAExtensionsBundle::loadExtension()}
 * when "notifications.mercure.enabled" is true AND symfony/mercure is installed — so
 * {@see MercureNotificationPublisher} (the only class in the bundle importing
 * `Symfony\Component\Mercure\...`) is never loaded/reflected otherwise.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services->set(MercureNotificationPublisher::class);

    $services->alias(NotificationPublisherInterface::class, MercureNotificationPublisher::class);
};
