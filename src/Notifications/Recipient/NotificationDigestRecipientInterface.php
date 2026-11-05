<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient;

/**
 * Optionally implemented by the recipient entity when some recipients prefer a periodic digest
 * of their notifications over one alert per notification. The bundle only flags the
 * notifications waiting for that digest ({@see \CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification::isPendingDigest()});
 * building and sending the digest itself (an email, usually) stays in the host app.
 */
interface NotificationDigestRecipientInterface extends NotificationRecipientInterface
{
    public function receivesNotificationDigest(): bool;
}
