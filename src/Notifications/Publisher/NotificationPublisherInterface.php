<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher;

interface NotificationPublisherInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $topic, array $payload): void;
}
