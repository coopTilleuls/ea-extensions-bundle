<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Manager;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin\NotificationCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event\AfterNotificationPersistedEvent;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event\BeforeNotificationPersistedEvent;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event\BeforeNotificationPublishedEvent;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher\NotificationPublisherInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Topic\NotificationTopicResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class NotificationManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NotificationPublisherInterface $publisher,
        private readonly NotificationTopicResolverInterface $topicResolver,
    ) {
    }

    /**
     * @param list<NotificationRecipientInterface> $recipients
     *
     * @return list<Notification>
     */
    public function send(Notification $notification, array $recipients, bool $flush = false): array
    {
        $notification->setDate($this->clock->now())
            ->setRead(false);

        /** @var list<Notification> $notifications */
        $notifications = [];
        foreach ($recipients as $recipient) {
            $notificationToSend = clone $notification;
            $notificationToSend->setRecipient($recipient);
            if (null !== $notificationToSend->getTreatment() && !$notification->isTreatmentShared()) {
                $notificationToSend->setTreatment(clone $notificationToSend->getTreatment());
            }

            $this->eventDispatcher->dispatch(new BeforeNotificationPersistedEvent($notificationToSend));

            $this->entityManager->persist($notificationToSend);
            $notifications[] = $notificationToSend;
        }

        if ($flush) {
            $this->entityManager->flush();

            foreach ($notifications as $notificationToSend) {
                $this->eventDispatcher->dispatch(new AfterNotificationPersistedEvent($notificationToSend));
            }

            // Publishing needs a persisted id (used in the payload's detail URL), so it can only
            // happen once we know a flush actually ran.
            foreach ($notifications as $notificationToSend) {
                $this->publish($notificationToSend);
            }
        }

        return $notifications;
    }

    private function publish(Notification $notification): void
    {
        $topic = $this->topicResolver->resolve($notification->getRecipient());

        $payload = [
            'topic' => $topic,
            'url' => $this->adminUrlGenerator
                ->unsetAll()
                ->setController(NotificationCrudController::class)
                ->setAction(Action::DETAIL)
                ->set('entityId', $notification->getId())
                ->generateUrl(),
            'title' => $notification->getTitle(),
            'type' => $notification->getType(),
        ];

        $event = new BeforeNotificationPublishedEvent($notification, $topic, $payload);
        $this->eventDispatcher->dispatch($event);

        if ($event->isPropagationStopped()) {
            return;
        }

        $this->publisher->publish($event->getTopic(), $event->getPayload());
    }
}
