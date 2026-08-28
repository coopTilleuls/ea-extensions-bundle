<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureEmbeddedTargetCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureEmbeddingCrudController;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class CrudFieldsTest extends AbstractFunctionalTestCase
{
    public function testEmbeddedTargetShowsItsOwnActionsByDefault(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureEmbeddedTargetCrudController::class));

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('.action-new')->count());
    }

    public function testHideActionsQueryFlagsHideActionsWithZeroChangesOnTheTargetController(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureEmbeddedTargetCrudController::class, extraParams: [
            'hideGlobalActions' => '1',
            'hideEntityActions' => '1',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSame(0, $crawler->filter('.action-new')->count());
        self::assertSame(0, $crawler->filter('.action-edit')->count());
        self::assertSame(0, $crawler->filter('.action-delete')->count());
    }

    public function testHideGlobalActionsAloneLeavesEntityActionsUntouched(): void
    {
        $this->persistFixtureEntity('Fixture title');
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureEmbeddedTargetCrudController::class, extraParams: [
            'hideGlobalActions' => '1',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSame(0, $crawler->filter('.action-new')->count());
        self::assertGreaterThan(0, $crawler->filter('.action-edit')->count());
    }

    public function testEmbeddingDetailPageRendersTheEmbeddedTargetsRows(): void
    {
        $entityId = $this->persistFixtureEntity('Fixture title');
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureEmbeddingCrudController::class, Action::DETAIL, [
            'entityId' => (string) $entityId,
        ]));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Fixture title', $crawler->text());
    }

    public function testOverrideIndexUrlDrivesTheSeeAllLinkByDefault(): void
    {
        $entityId = $this->persistFixtureEntity('Fixture title');
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureEmbeddingCrudController::class, Action::DETAIL, [
            'entityId' => (string) $entityId,
        ]));

        self::assertResponseIsSuccessful();
        $seeAllLink = $crawler->filter('.action-see-all')->attr('href');

        self::assertNotNull($seeAllLink);
        self::assertStringContainsString('filters%5Btitle%5D%5Bvalue%5D=fixture', $seeAllLink);
    }

    public function testLazyEmbedDefersInitialLoad(): void
    {
        $entityId = $this->persistFixtureEntity('Fixture title');
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureEmbeddingCrudController::class, Action::DETAIL, [
            'entityId' => (string) $entityId,
        ]));

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('.ea-crud-field-lazy')->count());
        self::assertSame(0, $crawler->filter('.ea-crud-field-lazy table')->count());
    }

    public function testDisplayAsCardWrapsTheFieldInACard(): void
    {
        $entityId = $this->persistFixtureEntity('Fixture title');
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureEmbeddingCrudController::class, Action::DETAIL, [
            'entityId' => (string) $entityId,
        ]));

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('div.card .action-see-all')->count());
    }

    public function testShowFiltersRendersTheFilterButtonOnlyOnTheEmbedThatAsksForIt(): void
    {
        $entityId = $this->persistFixtureEntity('Fixture title');
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureEmbeddingCrudController::class, Action::DETAIL, [
            'entityId' => (string) $entityId,
        ]));

        self::assertResponseIsSuccessful();
        // "embedFiltered" has showFilters(); "embedPlain" (eager, no showFilters) does not —
        // even though the target controller itself has filters configured, the button must
        // stay hidden unless the CrudField explicitly asked for it.
        self::assertSame(1, $crawler->filter('.action-filters-button')->count());
    }

    public function testActionLinksCarryDataActionNameForTheModalJsToKeyOff(): void
    {
        $this->persistFixtureEntity('Fixture title');
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureEmbeddedTargetCrudController::class));

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('a.action-new[data-action-name="new"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a.action-edit[data-action-name="edit"]')->count());
    }

    public function testCleanLayoutStripsTheNewActionPageWithZeroChangesOnTheTargetController(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureEmbeddedTargetCrudController::class, Action::NEW, [
            'cleanLayout' => '1',
        ]));

        self::assertResponseIsSuccessful();
        // The real, full "new" page always has a <title> (rendered by the real layout's head
        // block) — our stripped embedded_layout.html.twig renders no head content whatsoever,
        // so its absence here proves the SAME generic layout override that already strips
        // crud/index for the plain embed also strips crud/new, with zero changes to this
        // controller (it's a plain AbstractCrudController, see the class itself).
        self::assertSame(0, $crawler->filter('title')->count());
        self::assertGreaterThan(0, $crawler->filter('form')->count());
    }

    public function testOpenActionsInModalRendersItsValueOnlyOnTheEmbedThatAsksForIt(): void
    {
        $entityId = $this->persistFixtureEntity('Fixture title');
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureEmbeddingCrudController::class, Action::DETAIL, [
            'entityId' => (string) $entityId,
        ]));

        self::assertResponseIsSuccessful();
        self::assertSame(
            1,
            $crawler->filter('[data-ea-extensions--crud-field-eager-open-actions-in-modal-value="true"]')->count(),
        );
        self::assertGreaterThan(
            0,
            $crawler->filter('[data-ea-extensions--crud-field-eager-open-actions-in-modal-value="false"]')->count(),
        );
        self::assertGreaterThan(0, $crawler->filter('template#ea-extensions-crud-field-action-modal-template')->count());
    }

    public function testForcedFilterDoesNotCountAsAppliedAndIsHiddenFromTheFiltersForm(): void
    {
        $entityId = $this->persistFixtureEntity('Fixture title');
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureEmbeddingCrudController::class, Action::DETAIL, [
            'entityId' => (string) $entityId,
        ]));
        self::assertResponseIsSuccessful();

        // "embedFiltered" is the only eager (non-lazy) embed wrapped in a card.
        $fetchUrl = $crawler->filter('div.card [data-ea-extensions--crud-field-eager-crud-path-value]')->attr('data-ea-extensions--crud-field-eager-crud-path-value');
        self::assertNotNull($fetchUrl);

        $embedCrawler = $this->client->request('GET', $fetchUrl);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $embedCrawler->filter('.action-filters-button.action-filters-applied')->count());
        self::assertSame(0, $embedCrawler->filter('.action-filters-button-count')->count());

        $filterButtonHref = $embedCrawler->filter('.action-filters-button')->attr('data-href');
        self::assertNotNull($filterButtonHref);

        $filtersFormCrawler = $this->client->request('GET', $filterButtonHref);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $filtersFormCrawler->filter('.filter-field[data-filter-property="title"]')->count());
        self::assertGreaterThan(0, $filtersFormCrawler->filter('input[type="hidden"][name*="title"]')->count());
    }

    private function persistFixtureEntity(string $title): int
    {
        $entity = new FixtureEntity();
        $entity->setTitle($title);
        $entity->setDescription('Description');
        $entity->setNote('Note');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();

        return $entity->getId() ?? throw new \LogicException('Fixture entity was not assigned an id after flush.');
    }
}
