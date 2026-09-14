<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event;

/**
 * Dispatched once per recipient clone, before it is persisted — lets the host app tailor a
 * notification per recipient (e.g. set a different {@see \CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification::$component}
 * depending on who the recipient is).
 */
final class BeforeNotificationPersistedEvent extends NotificationEvent
{
}
