<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\Notifications\Publisher;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher\NullNotificationPublisher;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

final class NullNotificationPublisherTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testPublishIsANoOp(): void
    {
        $publisher = new NullNotificationPublisher();

        $publisher->publish('/notifications/1', ['title' => 'Title']);
    }
}
