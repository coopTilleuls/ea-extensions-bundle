<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientResolverInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureNotificationRecipient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Stands in for what a real host app provides: maps the logged-in Security user to its own
 * recipient entity (here, by username) instead of requiring the Security User class itself to
 * implement {@see NotificationRecipientInterface}.
 */
final class FixtureNotificationRecipientResolver implements NotificationRecipientResolverInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolveCurrentRecipient(): ?NotificationRecipientInterface
    {
        $identifier = $this->security->getUser()?->getUserIdentifier();

        if (null === $identifier) {
            return null;
        }

        return $this->entityManager->getRepository(FixtureNotificationRecipient::class)->findOneBy(['username' => $identifier]);
    }
}
