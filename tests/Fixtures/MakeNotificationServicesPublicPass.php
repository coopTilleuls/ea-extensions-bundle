<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Manager\NotificationManager;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher\NotificationPublisherInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Repository\NotificationRepository;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Exposes bundle-internal services tests need to fetch directly via
 * static::getContainer()->get(). Runs as a compiler pass (not from Kernel::configureContainer())
 * because it must run after the bundle's own extension has registered these services.
 */
final class MakeNotificationServicesPublicPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ([NotificationManager::class, NotificationPublisherInterface::class, NotificationRepository::class] as $id) {
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->setPublic(true);
            } elseif ($container->hasAlias($id)) {
                $container->getAlias($id)->setPublic(true);
            }
        }
    }
}
