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
use Symfony\Component\Security\Core\User\InMemoryUser;

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

    /**
     * The other half of the private-update fix: _notification_popover.html.twig's mercure() call
     * must actually request a subscriber JWT scoped to the current recipient's own topic — that's
     * what makes symfony/mercure's Authorization::setCookie() attach the cookie a browser needs
     * to receive that (now private) update at all. Without this, the fix above would silently
     * stop working (the popover would just never receive anything) with nothing catching it.
     */
    public function testThePopoverRequestsASubscriberCookieScopedToTheCurrentRecipientsOwnTopic(): void
    {
        $admin = new FixtureNotificationRecipient('admin');
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($admin);
        $entityManager->flush();

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $this->client->request('GET', '/_fixtures/notification-popover');

        self::assertResponseIsSuccessful();

        $cookie = $this->client->getResponse()->headers->getCookies()[0] ?? null;
        self::assertNotNull($cookie, 'Expected a subscriber-authorization cookie on the response.');
        self::assertSame('mercureAuthorization', $cookie->getName());

        [, $encodedPayload] = explode('.', (string) $cookie->getValue());
        $claims = json_decode(base64_decode(strtr($encodedPayload, '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['/notifications/'.$admin->getId()], $claims['mercure']['subscribe']);
    }

    public function testThePopoverTellsTheHubControllerWhichProtocolToSpeak(): void
    {
        $admin = new FixtureNotificationRecipient('admin');
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($admin);
        $entityManager->flush();

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', '/_fixtures/notification-popover');

        self::assertResponseIsSuccessful();
        // RecordingMercureHub speaks the legacy protocol, which is also the fallback on symfony/mercure < 0.9.
        self::assertSame('0.x', $crawler->filter('[data-controller~="ea-extensions--mercure-hub"]')->attr('data-ea-extensions--mercure-hub-protocol-version-value'));
    }
}
