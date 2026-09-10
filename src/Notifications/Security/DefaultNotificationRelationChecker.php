<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientResolverInterface;

/**
 * Default relation check: related if the notification's recipient is one of
 * {@see NotificationRecipientResolverInterface::resolveCurrentRecipients()}.
 */
final class DefaultNotificationRelationChecker implements NotificationRelationCheckerInterface
{
    public function __construct(
        private readonly NotificationRecipientResolverInterface $recipientResolver,
    ) {
    }

    public function isRelated(Notification $notification): bool
    {
        foreach ($this->recipientResolver->resolveCurrentRecipients() as $recipient) {
            if ($notification->getRecipient()->getId() === $recipient->getId()) {
                return true;
            }
        }

        return false;
    }
}
