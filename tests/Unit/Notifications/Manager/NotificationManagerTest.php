<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\Notifications\Manager;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event\AfterNotificationPersistedEvent;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event\BeforeNotificationPersistedEvent;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Event\BeforeNotificationPublishedEvent;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Manager\NotificationManager;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher\NotificationPublisherInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Topic\NotificationTopicResolverInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\StubNotificationRecipient;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class NotificationManagerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private AdminUrlGeneratorInterface&MockObject $adminUrlGenerator;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private NotificationPublisherInterface&MockObject $publisher;
    private NotificationTopicResolverInterface&MockObject $topicResolver;
    private NotificationManager $manager;
    private NotificationRecipientInterface $recipient1;
    private NotificationRecipientInterface $recipient2;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->adminUrlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $this->adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $this->adminUrlGenerator->method('setController')->willReturnSelf();
        $this->adminUrlGenerator->method('setAction')->willReturnSelf();
        $this->adminUrlGenerator->method('set')->willReturnSelf();
        $this->adminUrlGenerator->method('generateUrl')->willReturn('/admin/notification/1');

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $this->publisher = $this->createMock(NotificationPublisherInterface::class);
        $this->topicResolver = $this->createMock(NotificationTopicResolverInterface::class);
        $this->topicResolver->method('resolve')->willReturnCallback(static fn (NotificationRecipientInterface $recipient): string => '/notifications/'.$recipient->getId());

        $this->manager = new NotificationManager(
            $this->entityManager,
            new MockClock(),
            $this->adminUrlGenerator,
            $this->eventDispatcher,
            $this->publisher,
            $this->topicResolver,
        );

        $this->recipient1 = new StubNotificationRecipient('recipient-1');
        $this->recipient2 = new StubNotificationRecipient('recipient-2');
    }

    public function testSendClonesOneNotificationPerRecipient(): void
    {
        $notification = new Notification('information', 'Title');

        $this->entityManager->expects(self::exactly(2))->method('persist');

        $sent = $this->manager->send($notification, [$this->recipient1, $this->recipient2]);

        self::assertCount(2, $sent);
        self::assertSame($this->recipient1, $sent[0]->getRecipient());
        self::assertSame($this->recipient2, $sent[1]->getRecipient());
        self::assertNotSame($sent[0], $sent[1]);
        self::assertFalse($sent[0]->isRead());
    }

    public function testSendFlushesOnlyWhenAskedTo(): void
    {
        $notification = new Notification('information', 'Title');

        $this->entityManager->expects(self::never())->method('flush');

        $this->manager->send($notification, [$this->recipient1]);
    }

    public function testSendFlushesAndDispatchesAfterPersistedEventWhenAskedTo(): void
    {
        $notification = new Notification('information', 'Title');

        $this->entityManager->expects(self::once())->method('flush');

        $dispatchedEvents = [];
        $this->eventDispatcher->method('dispatch')->willReturnCallback(static function (Event $event) use (&$dispatchedEvents): Event {
            $dispatchedEvents[] = $event;

            return $event;
        });

        $this->manager->send($notification, [$this->recipient1], flush: true);

        $afterPersistedEvents = array_filter($dispatchedEvents, static fn (Event $event): bool => $event instanceof AfterNotificationPersistedEvent);
        self::assertCount(1, $afterPersistedEvents);
    }

    public function testSendDoesNotDispatchAfterPersistedEventWithoutFlush(): void
    {
        $notification = new Notification('information', 'Title');

        $dispatchedEvents = [];
        $this->eventDispatcher->method('dispatch')->willReturnCallback(static function (Event $event) use (&$dispatchedEvents): Event {
            $dispatchedEvents[] = $event;

            return $event;
        });

        $this->manager->send($notification, [$this->recipient1], flush: false);

        self::assertEmpty(array_filter($dispatchedEvents, static fn (Event $event): bool => $event instanceof AfterNotificationPersistedEvent));
    }

    public function testSendDispatchesBeforePersistedEventForEachRecipient(): void
    {
        $notification = new Notification('information', 'Title');

        $dispatchedEvents = [];
        $this->eventDispatcher->method('dispatch')->willReturnCallback(static function (Event $event) use (&$dispatchedEvents): Event {
            $dispatchedEvents[] = $event;

            return $event;
        });

        $this->manager->send($notification, [$this->recipient1, $this->recipient2]);

        $beforePersistedEvents = array_filter($dispatchedEvents, static fn (Event $event): bool => $event instanceof BeforeNotificationPersistedEvent);
        self::assertCount(2, $beforePersistedEvents);
    }

    public function testEachRecipientGetsItsOwnTreatmentCloneByDefault(): void
    {
        $notification = new Notification('validation', 'Title')->addTreatment();

        $sent = $this->manager->send($notification, [$this->recipient1, $this->recipient2]);

        self::assertNotSame($sent[0]->getTreatment(), $sent[1]->getTreatment());
    }

    public function testRecipientsShareTheSameTreatmentWhenExplicitlyShared(): void
    {
        $notification = new Notification('validation', 'Title')->addTreatment()->shareTreatment();

        $sent = $this->manager->send($notification, [$this->recipient1, $this->recipient2]);

        self::assertSame($sent[0]->getTreatment(), $sent[1]->getTreatment());
    }

    public function testSendPublishesToTheResolvedTopicForEachRecipient(): void
    {
        $notification = new Notification('information', 'Title');

        $publishedTopics = [];
        $this->publisher->method('publish')->willReturnCallback(static function (string $topic) use (&$publishedTopics): void {
            $publishedTopics[] = $topic;
        });

        $this->manager->send($notification, [$this->recipient1, $this->recipient2], flush: true);

        self::assertSame(['/notifications/recipient-1', '/notifications/recipient-2'], $publishedTopics);
    }

    public function testSendDoesNotPublishWithoutFlush(): void
    {
        $notification = new Notification('information', 'Title');

        $this->publisher->expects(self::never())->method('publish');

        $this->manager->send($notification, [$this->recipient1]);
    }

    public function testStoppingPropagationOnBeforePublishedEventSkipsPublishing(): void
    {
        $notification = new Notification('information', 'Title');

        $this->eventDispatcher->method('dispatch')->willReturnCallback(static function (Event $event): Event {
            if ($event instanceof BeforeNotificationPublishedEvent) {
                $event->stopPropagation();
            }

            return $event;
        });

        $this->publisher->expects(self::never())->method('publish');

        $this->manager->send($notification, [$this->recipient1], flush: true);
    }
}
