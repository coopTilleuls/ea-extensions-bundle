<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureLockedPaginationCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureMismatchedPaginationCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixturePaginationCrudController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class PaginationTest extends AbstractFunctionalTestCase
{
    public function testIndexUsesTheBundleDefaultPageSizeForAPlainController(): void
    {
        $this->persistFixtureEntities(3);
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudController::class));

        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('tbody tr'));
        self::assertGreaterThan(0, $crawler->filter('select[data-controller="ea-extensions--pagination-page-size"]')->count());
    }

    public function testQueryOverrideChangesThePageSize(): void
    {
        $this->persistFixtureEntities(5);
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudController::class, extraParams: [
            'itemsPerPage' => '2',
        ]));

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('tbody tr'));
    }

    public function testNegativeQueryOverrideDoesNotCrashAndFallsBackToTheDefault(): void
    {
        $this->persistFixtureEntities(3);
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $this->client->request('GET', $this->crudUrl(FixtureCrudController::class, extraParams: [
            'itemsPerPage' => '-1',
        ]));

        self::assertResponseIsSuccessful();
    }

    public function testControllerDeclaredDefaultAndAvailableSizesApply(): void
    {
        $this->persistFixtureEntities(5);
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixturePaginationCrudController::class));

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('tbody tr'));
        self::assertSame(
            ['2', '5'],
            $crawler->filter('select[data-controller="ea-extensions--pagination-page-size"] option')->each(
                static fn ($node): string => trim($node->text()),
            ),
        );
    }

    public function testControllerDeclaredDefaultNotInItsOwnAvailableSizesThrows(): void
    {
        $this->client->catchExceptions(false);
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is not one of the page sizes returned by');

        $this->client->request('GET', $this->crudUrl(FixtureMismatchedPaginationCrudController::class));
    }

    public function testLockedControllerHidesTheSelectorAndIgnoresTheQueryOverride(): void
    {
        $this->persistFixtureEntities(5);
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureLockedPaginationCrudController::class, extraParams: [
            'itemsPerPage' => '1',
        ]));

        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('tbody tr'));
        self::assertCount(0, $crawler->filter('select[data-controller="ea-extensions--pagination-page-size"]'));
    }

    private function persistFixtureEntities(int $count): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        for ($i = 0; $i < $count; ++$i) {
            $entity = new FixtureEntity();
            $entity->setTitle('Title '.$i);
            $entity->setDescription('Description');
            $entity->setNote('Note');
            $entityManager->persist($entity);
        }

        $entityManager->flush();
    }
}
