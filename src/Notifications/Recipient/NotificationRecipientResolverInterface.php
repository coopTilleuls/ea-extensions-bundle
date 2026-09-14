<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient;

interface NotificationRecipientResolverInterface
{
    /**
     * Resolves the single identity real-time updates are scoped to (the Mercure topic to
     * subscribe to). Returns null when there is no resolvable recipient (e.g. anonymous user).
     */
    public function resolveCurrentRecipient(): ?NotificationRecipientInterface;

    /**
     * Resolves every identity whose notifications count as "mine" for reads (index, unread
     * count, latest-notifications card, {@see \CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security\NotificationRelationCheckerInterface}).
     * Usually just {@see self::resolveCurrentRecipient()} on its own — implement this
     * separately when a single logged-in user can hold more than one valid recipient identity
     * at once (e.g. a currently active one plus historical ones).
     *
     * @return iterable<NotificationRecipientInterface>
     */
    public function resolveCurrentRecipients(): iterable;
}
