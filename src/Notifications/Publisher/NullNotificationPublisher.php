<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher;

/**
 * Default publisher when Mercure is disabled (or not installed) — a no-op, so
 * {@see \CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Manager\NotificationManager}
 * can always depend on {@see NotificationPublisherInterface} unconditionally.
 */
final class NullNotificationPublisher implements NotificationPublisherInterface
{
    public function publish(string $topic, array $payload): void
    {
    }
}
