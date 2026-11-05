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
        $recipientId = $notification->getRecipient()->getId();
        if (null === $recipientId) {
            return false;
        }

        foreach ($this->recipientResolver->resolveCurrentRecipients() as $recipient) {
            if ($recipientId === $recipient->getId()) {
                return true;
            }
        }

        return false;
    }
}
