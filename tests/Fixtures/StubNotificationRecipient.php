<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientInterface;

/**
 * Plain (non-Doctrine) {@see NotificationRecipientInterface} implementation for unit tests that
 * don't need a real entity manager — {@see FixtureNotificationRecipient} is the Doctrine-backed
 * equivalent used in functional tests.
 */
final class StubNotificationRecipient implements NotificationRecipientInterface
{
    public function __construct(
        private readonly int|string $id,
    ) {
    }

    public function getId(): int|string
    {
        return $this->id;
    }
}
