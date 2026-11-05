<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin\NotificationCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Manager\NotificationManager;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Repository\NotificationRepository;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\CustomNotificationCrudKernel;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureNotificationRecipient;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureNotificationCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\RecordingNotificationEventListener;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class NotificationsCustomCrudTest extends AbstractFunctionalTestCase
{
    protected static function getKernelClass(): string
    {
        return CustomNotificationCrudKernel::class;
    }

    public function testTheBundleCrudControllerGetsNoRouteOnceReplaced(): void
    {
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);
        $controllers = array_map(static fn (Route $route): mixed => $route->getDefault('crudControllerFqcn'), $router->getRouteCollection()->all());

        self::assertContains(FixtureNotificationCrudController::class, $controllers);
        self::assertNotContains(NotificationCrudController::class, $controllers);
    }

    public function testViewingTheDetailPageOfTheConfiguredCrudMarksTheNotificationAsRead(): void
    {
        $admin = new FixtureNotificationRecipient('admin');
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($admin);
        $entityManager->flush();

        /** @var NotificationManager $manager */
        $manager = static::getContainer()->get(NotificationManager::class);
        [$sent] = $manager->send(new Notification('information', 'Some notification'), [$admin], flush: true);

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $this->client->request('GET', $this->crudUrl(FixtureNotificationCrudController::class, Action::DETAIL, ['entityId' => (string) $sent->getId()]));

        self::assertResponseIsSuccessful();

        /** @var NotificationRepository $repository */
        $repository = static::getContainer()->get(NotificationRepository::class);
        self::assertTrue($repository->find($sent->getId())?->isRead());

        /** @var RecordingNotificationEventListener $listener */
        $listener = static::getContainer()->get(RecordingNotificationEventListener::class);
        self::assertCount(1, $listener->markedAsReadEvents);
    }

    public function testTogglingTheTreatmentFromTheConfiguredCrudDispatchesTheEvent(): void
    {
        $admin = new FixtureNotificationRecipient('admin');
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($admin);
        $entityManager->flush();

        /** @var NotificationManager $manager */
        $manager = static::getContainer()->get(NotificationManager::class);
        [$sent] = $manager->send(new Notification('validation', 'Needs action')->addTreatment(), [$admin], flush: true);

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', $this->crudUrl(FixtureNotificationCrudController::class));
        $toggleUrl = $crawler->filter('td[data-column="treatment.pending"] input[data-toggle-url]')->attr('data-toggle-url');
        self::assertNotEmpty($toggleUrl);

        $this->client->request('PATCH', $toggleUrl.'&newValue=false', server: ['HTTP_X-Requested-With' => 'XMLHttpRequest']);

        self::assertResponseIsSuccessful();
        /** @var NotificationRepository $repository */
        $repository = static::getContainer()->get(NotificationRepository::class);
        self::assertFalse($repository->find($sent->getId())?->getTreatment()?->isPending());

        /** @var RecordingNotificationEventListener $listener */
        $listener = static::getContainer()->get(RecordingNotificationEventListener::class);
        self::assertCount(1, $listener->treatmentChangedEvents);
    }
}
