<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event;

/**
 * Dispatched once per persisted recipient clone, only when
 * {@see \CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Manager\NotificationManager::send()}
 * was called with `flush: true` (otherwise the notification has no id yet). Lets the host app
 * hook side effects tied to persistence (e.g. sending a companion email).
 */
final class AfterNotificationPersistedEvent extends NotificationEvent
{
}
