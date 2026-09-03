<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Manager\NotificationManager;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher\MercureNotificationPublisher;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Publisher\NotificationPublisherInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureNotificationRecipient;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\MercureKernel;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\RecordingMercureHub;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationsMercureTest extends AbstractFunctionalTestCase
{
    protected static function getKernelClass(): string
    {
        return MercureKernel::class;
    }

    public function testTheMercurePublisherIsWiredInWhenEnabled(): void
    {
        self::assertInstanceOf(MercureNotificationPublisher::class, static::getContainer()->get(NotificationPublisherInterface::class));
    }

    public function testSendingANotificationPublishesItToTheResolvedTopic(): void
    {
        $admin = new FixtureNotificationRecipient('admin');
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($admin);
        $entityManager->flush();

        /** @var NotificationManager $manager */
        $manager = static::getContainer()->get(NotificationManager::class);
        [$sent] = $manager->send(new Notification('information', 'Real-time title'), [$admin], flush: true);

        /** @var RecordingMercureHub $hub */
        $hub = static::getContainer()->get(RecordingMercureHub::class);

        self::assertCount(1, $hub->publishedUpdates);
        $update = $hub->publishedUpdates[0];
        self::assertSame(['/notifications/'.$admin->getId()], $update->getTopics());
        self::assertTrue($update->isPrivate());

        $payload = json_decode($update->getData(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('/notifications/'.$admin->getId(), $payload['topic']);
        self::assertSame('Real-time title', $payload['title']);
        self::assertSame('information', $payload['type']);
        self::assertNotNull($sent->getId());
    }
}
