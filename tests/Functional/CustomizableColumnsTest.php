<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnSelectionResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureCrudControllerWithConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class CustomizableColumnsTest extends AbstractFunctionalTestCase
{
    public function testIndexShowsAllColumnsByDefaultForAPlainController(): void
    {
        $this->persistFixtureEntity();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudController::class));

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['id', 'title', 'description', 'note'],
            $this->getColumnProperties($crawler),
        );
    }

    public function testCustomizeColumnsActionIsPresent(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudController::class));

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('a:contains("Personnaliser les colonnes")')->count());
    }

    public function testQueryOverrideFiltersAndReordersColumns(): void
    {
        $this->persistFixtureEntity();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudController::class, extraParams: [
            ColumnSelectionResolver::QUERY_PARAM => 'note,title',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSame(['note', 'title'], $this->getColumnProperties($crawler));
    }

    public function testLockedColumnIsAlwaysIncludedAndDefaultColumnsApply(): void
    {
        $this->persistFixtureEntity();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudControllerWithConfiguration::class));

        self::assertResponseIsSuccessful();
        self::assertSame(['id', 'title'], $this->getColumnProperties($crawler));
    }

    public function testLockedColumnSurvivesAQueryOverrideThatOmitsIt(): void
    {
        $this->persistFixtureEntity();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudControllerWithConfiguration::class, extraParams: [
            ColumnSelectionResolver::QUERY_PARAM => 'note',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSame(['id', 'note'], $this->getColumnProperties($crawler));
    }

    public function testRenderEndpointPreChecksTheCurrentSelection(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', '/_ea-extensions/customizable-columns/render', [
            'crudControllerFqcn' => FixtureCrudController::class,
            ColumnSelectionResolver::QUERY_PARAM => 'note,title',
        ]);

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('input[type="checkbox"][checked]')->count());
    }

    public function testApplyingColumnsWithoutTheSaveSwitchIsTransientOnly(): void
    {
        $this->persistFixtureEntity();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $redirectUrl = $this->crudUrl(FixtureCrudController::class);

        $this->client->request(
            'POST',
            '/_ea-extensions/customizable-columns/save?'.http_build_query([
                'crudControllerFqcn' => FixtureCrudController::class,
                'redirectUrl' => $redirectUrl,
            ]),
            ['columns' => ['note' => '1', 'title' => '1']],
        );

        $location = $this->client->getResponse()->headers->get('Location');
        self::assertNotNull($location);
        self::assertStringContainsString(ColumnSelectionResolver::QUERY_PARAM.'=note%2Ctitle', $location);

        $crawler = $this->client->request('GET', $location);
        self::assertResponseIsSuccessful();
        self::assertSame(['note', 'title'], $this->getColumnProperties($crawler));

        // No "save" switch was submitted, so a fresh request without the override falls back to the default set.
        $crawler = $this->client->request('GET', $redirectUrl);
        self::assertResponseIsSuccessful();
        self::assertSame(['id', 'title', 'description', 'note'], $this->getColumnProperties($crawler));
    }

    public function testCheckingTheSaveSwitchPersistsAndAppliesOnTheNextRequestWithoutOverride(): void
    {
        $this->persistFixtureEntity();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $redirectUrl = $this->crudUrl(FixtureCrudController::class);

        $this->client->request(
            'POST',
            '/_ea-extensions/customizable-columns/save?'.http_build_query([
                'crudControllerFqcn' => FixtureCrudController::class,
                'redirectUrl' => $redirectUrl,
            ]),
            ['columns' => ['note' => '1', 'title' => '1', 'save' => '1']],
        );

        $location = $this->client->getResponse()->headers->get('Location');
        self::assertNotNull($location);

        $crawler = $this->client->request('GET', $redirectUrl);

        self::assertResponseIsSuccessful();
        self::assertSame(['note', 'title'], $this->getColumnProperties($crawler));
    }

    private function persistFixtureEntity(): void
    {
        $entity = new FixtureEntity();
        $entity->setTitle('Title');
        $entity->setDescription('Description');
        $entity->setNote('Note');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    /**
     * @return list<string>
     */
    private function getColumnProperties(Crawler $crawler): array
    {
        /** @var list<\DOMElement> $nodes */
        $nodes = iterator_to_array($crawler->filter('thead th[data-column]'), false);

        return array_map(
            static fn (\DOMElement $node): string => $node->getAttribute('data-column'),
            $nodes,
        );
    }
}
