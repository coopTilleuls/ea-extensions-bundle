<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event;

/**
 * Dispatched whenever a notification's treatment "pending" state changes through the CRUD's
 * inline toggle. The host app listens to this event to send its own follow-up notification.
 */
final class NotificationTreatmentChangedEvent extends NotificationEvent
{
}
