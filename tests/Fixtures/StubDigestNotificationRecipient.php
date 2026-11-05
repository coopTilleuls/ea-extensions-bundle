<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationDigestRecipientInterface;

final class StubDigestNotificationRecipient implements NotificationDigestRecipientInterface
{
    public function __construct(
        private readonly int|string $id,
        private readonly bool $digest,
    ) {
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function receivesNotificationDigest(): bool
    {
        return $this->digest;
    }
}
