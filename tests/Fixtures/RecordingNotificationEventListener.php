<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event\NotificationMarkedAsReadEvent;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event\NotificationTreatmentChangedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: NotificationTreatmentChangedEvent::class, method: 'onTreatmentChanged')]
#[AsEventListener(event: NotificationMarkedAsReadEvent::class, method: 'onMarkedAsRead')]
final class RecordingNotificationEventListener
{
    /**
     * @var list<NotificationTreatmentChangedEvent>
     */
    public array $treatmentChangedEvents = [];

    /**
     * @var list<NotificationMarkedAsReadEvent>
     */
    public array $markedAsReadEvents = [];

    public function onTreatmentChanged(NotificationTreatmentChangedEvent $event): void
    {
        $this->treatmentChangedEvents[] = $event;
    }

    public function onMarkedAsRead(NotificationMarkedAsReadEvent $event): void
    {
        $this->markedAsReadEvents[] = $event;
    }
}
