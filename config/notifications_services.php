<?php

declare(strict_types=1);

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Controller\LatestNotificationsController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin\NotificationCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher\NotificationPublisherInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher\NullNotificationPublisher;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientResolverInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\SecurityUserNotificationRecipientResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security\DefaultNotificationRelationChecker;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security\NotificationRelationCheckerInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Topic\DefaultNotificationTopicResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Topic\NotificationTopicResolverInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services->load('CoopTilleuls\\Bundle\\EAExtensionsBundle\\Notifications\\', __DIR__.'/../src/Notifications/')
        ->exclude([
            __DIR__.'/../src/Notifications/{Entity,Controller}',
            __DIR__.'/../src/Notifications/Publisher/MercureNotificationPublisher.php',
            __DIR__.'/../src/Notifications/Twig/NotificationMercureExtension.php',
            __DIR__.'/../src/Notifications/**/*Interface.php',
        ])
    ;

    $services->set(NotificationCrudController::class)
        ->tag('controller.service_arguments')
        ->public()
    ;

    $services->set(LatestNotificationsController::class)
        ->tag('controller.service_arguments')
        ->public()
    ;

    $services->alias(NotificationRecipientResolverInterface::class, SecurityUserNotificationRecipientResolver::class);
    $services->alias(NotificationRelationCheckerInterface::class, DefaultNotificationRelationChecker::class);
    $services->alias(NotificationTopicResolverInterface::class, DefaultNotificationTopicResolver::class);
    $services->alias(NotificationPublisherInterface::class, NullNotificationPublisher::class);
};
