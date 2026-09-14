<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient;

use Symfony\Bundle\SecurityBundle\Security;

/**
 * Default resolver: recipient = the current Security user, when that user's class itself
 * implements {@see NotificationRecipientInterface} (and `resolve_target_entities` is configured
 * accordingly). Returns null otherwise, including when the app's recipient concept isn't the
 * Security user itself — a host app in that situation provides its own resolver instead.
 */
final class SecurityUserNotificationRecipientResolver implements NotificationRecipientResolverInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function resolveCurrentRecipient(): ?NotificationRecipientInterface
    {
        $user = $this->security->getUser();

        return $user instanceof NotificationRecipientInterface ? $user : null;
    }

    public function resolveCurrentRecipients(): iterable
    {
        $recipient = $this->resolveCurrentRecipient();

        return null !== $recipient ? [$recipient] : [];
    }
}
