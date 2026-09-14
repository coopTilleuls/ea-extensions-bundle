<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;

/**
 * Dispatched once per recipient clone, right before it is handed to the
 * {@see \CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher\NotificationPublisherInterface}.
 * The host app can mutate the payload, or call {@see self::stopPropagation()} to skip
 * publishing this particular notification (e.g. a muted recipient).
 */
final class BeforeNotificationPublishedEvent extends NotificationEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        Notification $notification,
        private readonly string $topic,
        private array $payload,
    ) {
        parent::__construct($notification);
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }
}
