<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient;

/**
 * Implemented by whatever entity your app uses as a notification recipient/sender — the bundle
 * only ever depends on this interface, never on a concrete class. Point Doctrine at your
 * implementation with
 * `doctrine.orm.resolve_target_entities` in your own app config (see docs/notifications.md);
 * that's what turns {@see \CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification::$recipient}
 * into a real, joinable foreign key without the bundle knowing your schema.
 */
interface NotificationRecipientInterface
{
    public function getId(): int|string;
}
