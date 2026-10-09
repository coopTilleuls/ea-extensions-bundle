<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureIndexOnlySearchCrudController;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class IndexOnlySearchTest extends AbstractFunctionalTestCase
{
    private const string HEADER_SEARCH = '.content-top .form-action-search';

    #[DataProvider('providesListPages')]
    public function testHeaderSearchIsDisplayedOnLists(string $action): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $this->client->request('GET', $this->crudUrl(FixtureIndexOnlySearchCrudController::class, $action));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(self::HEADER_SEARCH);
        self::assertSelectorExists('.content-top.ea-search-enabled');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providesListPages(): iterable
    {
        yield 'index' => [Action::INDEX];
        yield 'custom list' => ['customList'];
    }

    #[DataProvider('providesNonListPages')]
    public function testHeaderSearchIsHiddenOutsideLists(string $action): void
    {
        $entity = new FixtureEntity();
        $entity->setTitle('Title');
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $this->client->request('GET', $this->crudUrl(FixtureIndexOnlySearchCrudController::class, $action, [
            'entityId' => (string) $entity->getId(),
        ]));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(self::HEADER_SEARCH);
        self::assertSelectorExists('.content-top.ea-search-disabled');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providesNonListPages(): iterable
    {
        yield 'detail' => [Action::DETAIL];
        yield 'edit' => [Action::EDIT];
        yield 'new' => [Action::NEW];
        yield 'custom page' => ['customPage'];
        yield 'custom page rendering its own response' => ['customRenderedPage'];
    }

    public function testSearchStillFiltersTheIndex(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach (['Dune', 'Hyperion'] as $title) {
            $entity = new FixtureEntity();
            $entity->setTitle($title);
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureIndexOnlySearchCrudController::class, extraParams: [
            'query' => 'Hyperion',
        ]));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('tbody tr'));
        self::assertSelectorTextContains('tbody tr', 'Hyperion');
    }
}
