<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use Symfony\Contracts\EventDispatcher\Event;

abstract class NotificationEvent extends Event
{
    public function __construct(
        private readonly Notification $notification,
    ) {
    }

    public function getNotification(): Notification
    {
        return $this->notification;
    }
}
