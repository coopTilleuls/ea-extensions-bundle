<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Only ever registered (and its `use Symfony\Component\Mercure\...` imports only ever loaded)
 * when "notifications.mercure.enabled" is true AND symfony/mercure is installed — see
 * config/notifications_services.php and config/notifications_mercure_services.php.
 */
final class MercureNotificationPublisher implements NotificationPublisherInterface
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function publish(string $topic, array $payload): void
    {
        try {
            // Private: only a subscriber presenting a JWT whose "mercure.subscribe" claim
            // covers this exact topic receives it — see _notification_popover.html.twig's
            // mercure() call, which requests that claim for the current recipient's own topic.
            $this->hub->publish(new Update([$topic], json_encode($payload, JSON_THROW_ON_ERROR), private: true));
        } catch (\Exception $exception) {
            $this->logger?->error('Error while publishing a notification to Mercure', ['exception' => $exception]);
        }
    }
}
