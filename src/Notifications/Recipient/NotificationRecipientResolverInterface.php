<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient;

interface NotificationRecipientResolverInterface
{
    /**
     * Resolves the recipient of the currently logged-in user, used to scope "my
     * notifications" reads (index, unread count, ...). Returns null when there is no
     * resolvable recipient (e.g. anonymous user).
     */
    public function resolveCurrentRecipient(): ?NotificationRecipientInterface;
}
