<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin\NotificationCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Manager\NotificationManager;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Repository\NotificationRepository;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureNotificationRecipient;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\RecordingNotificationEventListener;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class NotificationsTest extends AbstractFunctionalTestCase
{
    public function testIndexOnlyShowsTheCurrentRecipientsNotifications(): void
    {
        $admin = $this->createRecipient('admin');
        $someoneElse = $this->createRecipient('someone-else');

        $this->sendNotification('information', 'For admin', [$admin]);
        $this->sendNotification('information', 'For someone else', [$someoneElse]);

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', $this->crudUrl(NotificationCrudController::class));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('For admin', $crawler->text());
        self::assertStringNotContainsString('For someone else', $crawler->text());
    }

    public function testIndexCanBeSortedByTreatment(): void
    {
        $admin = $this->createRecipient('admin');

        /** @var NotificationManager $manager */
        $manager = static::getContainer()->get(NotificationManager::class);
        $manager->send(new Notification('validation', 'Needs action')->addTreatment(), [$admin], flush: true);
        $this->sendNotification('information', 'Nothing to treat', [$admin]);

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', $this->crudUrl(NotificationCrudController::class), [
            'sort' => [NotificationCrudController::ACTION_TREATMENT => 'DESC'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Needs action', $crawler->text());
        self::assertStringContainsString('Nothing to treat', $crawler->text());
    }

    public function testUnreadCountRouteReflectsOnlyTheCurrentRecipientsUnreadNotifications(): void
    {
        $admin = $this->createRecipient('admin');
        $someoneElse = $this->createRecipient('someone-else');

        $this->sendNotification('information', 'One', [$admin]);
        $this->sendNotification('information', 'Two', [$admin]);
        $this->sendNotification('information', 'Someone else', [$someoneElse]);

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $this->client->request('GET', $this->unreadCountUrl());

        self::assertResponseIsSuccessful();
        self::assertSame('2', $this->client->getResponse()->getContent());
    }

    public function testUnreadCountIsZeroWhenTheCurrentUserResolvesNoRecipient(): void
    {
        $admin = $this->createRecipient('admin');
        $this->sendNotification('information', 'One', [$admin]);

        // "unknown" has no matching FixtureNotificationRecipient row.
        $this->client->loginUser(new InMemoryUser('unknown', 'unknown', ['ROLE_ADMIN']));
        $this->client->request('GET', $this->unreadCountUrl());

        self::assertResponseIsSuccessful();
        self::assertSame('0', $this->client->getResponse()->getContent());
    }

    public function testViewingTheDetailPageMarksTheNotificationAsReadAndDispatchesTheEvent(): void
    {
        $admin = $this->createRecipient('admin');
        $notification = $this->sendNotification('information', 'Some notification', [$admin]);

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $this->client->request('GET', $this->crudUrl(NotificationCrudController::class, Action::DETAIL, ['entityId' => (string) $notification->getId()]));

        self::assertResponseIsSuccessful();

        /** @var NotificationRepository $repository */
        $repository = static::getContainer()->get(NotificationRepository::class);
        $reloaded = $repository->find($notification->getId());
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isRead());

        /** @var RecordingNotificationEventListener $listener */
        $listener = static::getContainer()->get(RecordingNotificationEventListener::class);
        self::assertCount(1, $listener->markedAsReadEvents);
    }

    public function testTreatmentToggleMarksItAsDoneAndDispatchesTheEvent(): void
    {
        $admin = $this->createRecipient('admin');

        $notification = new Notification('validation', 'Needs action')->addTreatment();
        /** @var NotificationManager $manager */
        $manager = static::getContainer()->get(NotificationManager::class);
        [$sent] = $manager->send($notification, [$admin], flush: true);

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        // the detail page's rendered toggle switch already carries a valid CSRF token, generated
        // server-side (fetching one directly from the container here has no request/session to
        // attach it to)
        $crawler = $this->client->request('GET', $this->crudUrl(NotificationCrudController::class, Action::DETAIL, ['entityId' => (string) $sent->getId()]));
        $toggleUrl = $crawler->filter('input[id="treatment.pending"]')->closest('[data-ea-extensions--notification-toggle-toggle-url-value]')?->attr('data-ea-extensions--notification-toggle-toggle-url-value');
        self::assertNotNull($toggleUrl);

        // EasyAdmin's inline boolean-toggle switches are driven by an XHR PATCH, not a plain
        // navigation — see AbstractCrudController::edit()'s isXmlHttpRequest()/'PATCH' branch.
        $this->client->request('PATCH', $toggleUrl.'&newValue=false', server: ['HTTP_X-Requested-With' => 'XMLHttpRequest']);

        self::assertResponseIsSuccessful();

        /** @var NotificationRepository $repository */
        $repository = static::getContainer()->get(NotificationRepository::class);
        $reloaded = $repository->find($sent->getId());
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->getTreatment()?->isPending());

        /** @var RecordingNotificationEventListener $listener */
        $listener = static::getContainer()->get(RecordingNotificationEventListener::class);
        self::assertCount(1, $listener->treatmentChangedEvents);
    }

    public function testTheTreatmentEventIsOnlyDispatchedWhenTheStateChanges(): void
    {
        $admin = $this->createRecipient('admin');

        /** @var NotificationManager $manager */
        $manager = static::getContainer()->get(NotificationManager::class);
        [$sent] = $manager->send(new Notification('validation', 'Needs action')->addTreatment(), [$admin], flush: true);

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', $this->crudUrl(NotificationCrudController::class, Action::DETAIL, ['entityId' => (string) $sent->getId()]));
        $toggleUrl = $crawler->filter('input[id="treatment.pending"]')->closest('[data-ea-extensions--notification-toggle-toggle-url-value]')?->attr('data-ea-extensions--notification-toggle-toggle-url-value');
        self::assertNotNull($toggleUrl);

        // the same value sent twice, e.g. from a second tab still showing the old state (the client
        // reboots the kernel, hence one listener per request)
        $this->client->request('PATCH', $toggleUrl.'&newValue=false', server: ['HTTP_X-Requested-With' => 'XMLHttpRequest']);
        /** @var RecordingNotificationEventListener $listener */
        $listener = static::getContainer()->get(RecordingNotificationEventListener::class);
        self::assertCount(1, $listener->treatmentChangedEvents);

        $this->client->request('PATCH', $toggleUrl.'&newValue=false', server: ['HTTP_X-Requested-With' => 'XMLHttpRequest']);
        self::assertResponseIsSuccessful();
        /** @var RecordingNotificationEventListener $listener */
        $listener = static::getContainer()->get(RecordingNotificationEventListener::class);
        self::assertCount(0, $listener->treatmentChangedEvents);
    }

    public function testTogglingTreatmentOnANotificationWithoutOneIsDenied(): void
    {
        $admin = $this->createRecipient('admin');

        $withoutTreatment = $this->sendNotification('information', 'No treatment here', [$admin]);

        $withTreatment = new Notification('validation', 'Has treatment')->addTreatment();
        /** @var NotificationManager $manager */
        $manager = static::getContainer()->get(NotificationManager::class);
        [$sentWithTreatment] = $manager->send($withTreatment, [$admin], flush: true);

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        // Borrow a validly session-bound CSRF token from a notification that DOES have a
        // treatment (the toggle switch, and its token, only render in that case) rather than
        // minting one directly — see testTreatmentToggleMarksItAsDoneAndDispatchesTheEvent's own
        // note on this — then repoint it at the one without.
        $crawler = $this->client->request('GET', $this->crudUrl(NotificationCrudController::class, Action::DETAIL, ['entityId' => (string) $sentWithTreatment->getId()]));
        $toggleUrl = $crawler->filter('input[id="treatment.pending"]')->closest('[data-ea-extensions--notification-toggle-toggle-url-value]')?->attr('data-ea-extensions--notification-toggle-toggle-url-value');
        self::assertNotNull($toggleUrl);
        parse_str((string) parse_url($toggleUrl, \PHP_URL_QUERY), $query);
        \assert(\is_string($query['csrfToken']));

        $forgedUrl = $this->crudUrl(NotificationCrudController::class, Action::EDIT, [
            'entityId' => (string) $withoutTreatment->getId(),
            'fieldName' => NotificationCrudController::ACTION_TREATMENT,
            'csrfToken' => $query['csrfToken'],
        ]);

        $this->client->request('PATCH', $forgedUrl.'&newValue=true', server: ['HTTP_X-Requested-With' => 'XMLHttpRequest']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testNotificationsWaitForTheDigestOnlyForRecipientsWhoReceiveOne(): void
    {
        $digest = $this->createRecipient('digest', digest: true);
        $instant = $this->createRecipient('instant');

        $this->sendNotification('information', 'First', [$digest, $instant]);
        $this->sendNotification('information', 'Second', [$digest]);

        /** @var NotificationRepository $repository */
        $repository = static::getContainer()->get(NotificationRepository::class);

        self::assertSame([$digest->getId()], $repository->findRecipientIdsPendingDigest());
        self::assertSame(['First', 'Second'], array_map(static fn (Notification $notification): string => $notification->getTitle(), $repository->findPendingDigest($digest->getId())));
        self::assertSame([], $repository->findPendingDigest($instant->getId()));
    }

    private function unreadCountUrl(): string
    {
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);

        return $router->generate('ea_extensions_notifications_unread_count');
    }

    private function createRecipient(string $username, bool $digest = false): FixtureNotificationRecipient
    {
        $recipient = new FixtureNotificationRecipient($username, $digest);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($recipient);
        $entityManager->flush();

        return $recipient;
    }

    /**
     * @param list<NotificationRecipientInterface> $recipients
     */
    private function sendNotification(string $type, string $title, array $recipients): Notification
    {
        /** @var NotificationManager $manager */
        $manager = static::getContainer()->get(NotificationManager::class);
        [$sent] = $manager->send(new Notification($type, $title), $recipients, flush: true);

        return $sent;
    }
}
