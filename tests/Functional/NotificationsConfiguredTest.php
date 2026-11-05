<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin\NotificationCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Manager\NotificationManager;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\ConfiguredNotificationsKernel;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureNotificationRecipient;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class NotificationsConfiguredTest extends AbstractFunctionalTestCase
{
    protected static function getKernelClass(): string
    {
        return ConfiguredNotificationsKernel::class;
    }

    public function testTheIndexShowsTypeLabelsAndFiltersOnThem(): void
    {
        $this->sendNotification('validation', 'Needs validation');
        $this->sendNotification('information', 'For your information');

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', $this->crudUrl(NotificationCrudController::class), ['filters' => ['type' => ['comparison' => '=', 'value' => 'validation']]]);

        self::assertResponseIsSuccessful();
        self::assertSame(['Validation'], $crawler->filter('td[data-column="type"]')->each(static fn ($cell): string => trim($cell->text())));
        self::assertStringNotContainsString('For your information', $crawler->filter('table.datagrid')->text());
    }

    public function testTheDetailShowsTheTypeLabelAndTheDateInTheConfiguredTimezone(): void
    {
        $notification = $this->sendNotification('validation', 'Needs validation');
        $notification->setDate(new \DateTimeImmutable('2026-01-15 12:00', new \DateTimeZone('UTC')));
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', $this->crudUrl(NotificationCrudController::class, Action::DETAIL, ['entityId' => (string) $notification->getId()]));

        self::assertResponseIsSuccessful();
        self::assertSame('Validation - Needs validation', trim($crawler->filter('.form-fieldset-title-content')->text()));
        self::assertStringContainsString('15/01/26 - 13h00', $crawler->filter('.form-fieldset-title')->text());
    }

    private function sendNotification(string $type, string $title): Notification
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $entityManager->getRepository(FixtureNotificationRecipient::class)->findOneBy(['username' => 'admin']) ?? new FixtureNotificationRecipient('admin');
        $entityManager->persist($admin);
        $entityManager->flush();

        /** @var NotificationManager $manager */
        $manager = static::getContainer()->get(NotificationManager::class);
        [$sent] = $manager->send(new Notification($type, $title), [$admin], flush: true);

        return $sent;
    }
}
