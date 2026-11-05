<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationDigestRecipientInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FixtureNotificationRecipient implements NotificationDigestRecipientInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $username;

    #[ORM\Column(type: 'boolean')]
    private bool $digest;

    public function __construct(string $username, bool $digest = false)
    {
        $this->username = $username;
        $this->digest = $digest;
    }

    public function getId(): int|string
    {
        return $this->id ?? throw new \LogicException(self::class.' has not been persisted yet.');
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function receivesNotificationDigest(): bool
    {
        return $this->digest;
    }
}
