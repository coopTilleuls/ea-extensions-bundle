<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnSelectionResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin\NotificationCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureCrudControllerWithColumnNames;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureCrudControllerWithConfiguration;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureCrudControllerWithFieldsFilter;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureCrudControllerWithUserDefaultColumns;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureDashboardController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureDefaultFieldsCrudController;
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
        $button = $crawler->filter('button:contains("Personnaliser les colonnes")');
        self::assertCount(1, $button);
        self::assertSame('button', $button->attr('type'));
        self::assertNotNull($button->attr('disabled'));
        self::assertStringStartsWith(
            '/_ea-extensions/customizable-columns/render?',
            (string) $button->attr('data-ea-extensions--customizable-columns-modal-url-value'),
        );
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

    public function testDefaultColumnsCanDependOnTheCurrentUser(): void
    {
        $this->persistFixtureEntity();

        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudControllerWithUserDefaultColumns::class));
        self::assertResponseIsSuccessful();
        self::assertSame(['id', 'title', 'note'], $this->getColumnProperties($crawler));

        $this->client->loginUser(new InMemoryUser('user', 'user', ['ROLE_USER']));
        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudControllerWithUserDefaultColumns::class));
        self::assertResponseIsSuccessful();
        self::assertSame(['id'], $this->getColumnProperties($crawler));
    }

    public function testRenderEndpointPreChecksTheDefaultColumnsOfTheCurrentUser(): void
    {
        $this->client->loginUser(new InMemoryUser('user', 'user', ['ROLE_USER']));

        $crawler = $this->client->request('GET', '/_ea-extensions/customizable-columns/render', [
            'crudControllerFqcn' => FixtureCrudControllerWithUserDefaultColumns::class,
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[type="checkbox"][name="columns[id]"][checked]'));
        self::assertCount(0, $crawler->filter('input[type="checkbox"][name="columns[title]"][checked]'));
        self::assertCount(1, $crawler->filter('input[type="checkbox"][name="columns[title]"]'));
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

    public function testCustomizeColumnsActionLinksToThePickerOfItsDashboard(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudController::class));

        $url = (string) $crawler->filter('.action-customizeColumns')->attr('data-ea-extensions--customizable-columns-modal-url-value');
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertSame(FixtureCrudController::class, $query['crudControllerFqcn']);
        self::assertSame(FixtureDashboardController::class, $query['dashboardControllerFqcn']);
    }

    /**
     * Without configureFields(), EasyAdmin guesses the fields from the entity of its admin
     * context, which the picker's own routes (not EasyAdmin ones) have to provide.
     */
    public function testRenderEndpointWorksForAControllerWithoutConfigureFields(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', '/_ea-extensions/customizable-columns/render', [
            'crudControllerFqcn' => FixtureDefaultFieldsCrudController::class,
            'dashboardControllerFqcn' => FixtureDashboardController::class,
            'redirectUrl' => $this->crudUrl(FixtureDefaultFieldsCrudController::class),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['columns[id]' => 'ID', 'columns[title]' => 'Title', 'columns[description]' => 'Description', 'columns[note]' => 'Note'],
            $this->getPickerColumns($crawler),
        );
        // the save URL keeps the dashboard, so applying the columns resolves the same fields
        self::assertStringContainsString(
            'dashboardControllerFqcn='.urlencode(FixtureDashboardController::class),
            (string) $crawler->filter('form')->attr('action'),
        );
    }

    public function testRenderEndpointFallsBackToTheOnlyDashboard(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', '/_ea-extensions/customizable-columns/render', [
            'crudControllerFqcn' => FixtureDefaultFieldsCrudController::class,
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(4, $this->getPickerColumns($crawler));
    }

    public function testRenderEndpointRejectsAnUnknownDashboard(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $this->client->request('GET', '/_ea-extensions/customizable-columns/render', [
            'crudControllerFqcn' => FixtureDefaultFieldsCrudController::class,
            'dashboardControllerFqcn' => FixtureCrudController::class,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testApplyingColumnsOfAControllerWithoutConfigureFields(): void
    {
        $this->persistFixtureEntity();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $this->client->request(
            'POST',
            '/_ea-extensions/customizable-columns/save?'.http_build_query([
                'crudControllerFqcn' => FixtureDefaultFieldsCrudController::class,
                'dashboardControllerFqcn' => FixtureDashboardController::class,
                'redirectUrl' => $this->crudUrl(FixtureDefaultFieldsCrudController::class),
            ]),
            ['columns' => ['note' => '1', 'title' => '1']],
        );

        $location = $this->client->getResponse()->headers->get('Location');
        self::assertNotNull($location);

        $crawler = $this->client->request('GET', $location);
        self::assertResponseIsSuccessful();
        self::assertSame(['note', 'title'], $this->getColumnProperties($crawler));
    }

    /**
     * "treatment.pending" can't be the name of a form field (dots aren't allowed in them).
     */
    public function testColumnsOfAnAssociatedEntityCanBePickedAndApplied(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        $redirectUrl = $this->crudUrl(NotificationCrudController::class);

        $crawler = $this->client->request('GET', '/_ea-extensions/customizable-columns/render', [
            'crudControllerFqcn' => NotificationCrudController::class,
            'redirectUrl' => $redirectUrl,
        ]);

        self::assertResponseIsSuccessful();
        $columns = $this->getPickerColumns($crawler);
        self::assertSame('Traitement', $columns['columns[treatment:pending]'] ?? null);

        $this->client->request(
            'POST',
            (string) $crawler->filter('form')->attr('action'),
            ['columns' => ['treatment:pending' => '1', 'title' => '1']],
        );

        $location = $this->client->getResponse()->headers->get('Location');
        self::assertNotNull($location);
        self::assertStringContainsString(ColumnSelectionResolver::QUERY_PARAM.'=treatment.pending%2Ctitle', $location);
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

    public function testDefaultColumnsMatchOnColumnNameRatherThanOnGeneratedProperty(): void
    {
        $this->persistFixtureEntity();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudControllerWithColumnNames::class));

        self::assertResponseIsSuccessful();
        $properties = $this->getColumnProperties($crawler);
        self::assertCount(2, $properties);
        self::assertSame('id', $properties[0]);
        self::assertStringStartsWith(FixtureCrudControllerWithColumnNames::VIRTUAL_PROPERTY_PREFIX, $properties[1]);
    }

    public function testQueryOverrideUsesColumnNames(): void
    {
        $this->persistFixtureEntity();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudControllerWithColumnNames::class, extraParams: [
            ColumnSelectionResolver::QUERY_PARAM => 'computed,heading',
        ]));

        self::assertResponseIsSuccessful();
        $properties = $this->getColumnProperties($crawler);
        self::assertCount(2, $properties);
        self::assertStringStartsWith(FixtureCrudControllerWithColumnNames::VIRTUAL_PROPERTY_PREFIX, $properties[0]);
        self::assertSame('title', $properties[1]);
    }

    public function testRenderEndpointListsColumnNames(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', '/_ea-extensions/customizable-columns/render', [
            'crudControllerFqcn' => FixtureCrudControllerWithColumnNames::class,
        ]);

        self::assertResponseIsSuccessful();
        foreach (['id', 'heading', 'computed'] as $column) {
            self::assertCount(1, $crawler->filter(sprintf('input[type="checkbox"][name="columns[%s]"]', $column)));
        }
    }

    public function testSavedPreferenceSurvivesARegeneratedProperty(): void
    {
        $this->persistFixtureEntity();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $redirectUrl = $this->crudUrl(FixtureCrudControllerWithColumnNames::class);

        $this->client->request(
            'POST',
            '/_ea-extensions/customizable-columns/save?'.http_build_query([
                'crudControllerFqcn' => FixtureCrudControllerWithColumnNames::class,
                'redirectUrl' => $redirectUrl,
            ]),
            ['columns' => ['heading' => '1', 'computed' => '1', 'save' => '1']],
        );

        $crawler = $this->client->request('GET', $redirectUrl);

        self::assertResponseIsSuccessful();
        $properties = $this->getColumnProperties($crawler);
        self::assertCount(2, $properties);
        self::assertSame('title', $properties[0]);
        self::assertStringStartsWith(FixtureCrudControllerWithColumnNames::VIRTUAL_PROPERTY_PREFIX, $properties[1]);
    }

    public function testFieldsFilterSkipsFormattingHiddenColumns(): void
    {
        $this->persistFixtureEntity();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        FixtureCrudControllerWithFieldsFilter::$noteFormatCount = 0;

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudControllerWithFieldsFilter::class));

        self::assertResponseIsSuccessful();
        self::assertSame(['id', 'title'], $this->getColumnProperties($crawler));
        self::assertSame(0, FixtureCrudControllerWithFieldsFilter::$noteFormatCount);
    }

    public function testFieldsFilterAppliesTheQueryOverride(): void
    {
        $this->persistFixtureEntity();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));
        FixtureCrudControllerWithFieldsFilter::$noteFormatCount = 0;

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudControllerWithFieldsFilter::class, extraParams: [
            ColumnSelectionResolver::QUERY_PARAM => 'note,id',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSame(['note', 'id'], $this->getColumnProperties($crawler));
        self::assertSame(1, FixtureCrudControllerWithFieldsFilter::$noteFormatCount);
    }

    public function testRenderEndpointListsColumnsHiddenByTheFieldsFilter(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', '/_ea-extensions/customizable-columns/render', [
            'crudControllerFqcn' => FixtureCrudControllerWithFieldsFilter::class,
        ]);

        self::assertResponseIsSuccessful();
        foreach (['id', 'title', 'note'] as $column) {
            self::assertCount(1, $crawler->filter(sprintf('input[type="checkbox"][name="columns[%s]"]', $column)));
        }
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
     * @return array<string, string> the label of each column of the picker, keyed by the name of its checkbox
     */
    private function getPickerColumns(Crawler $crawler): array
    {
        $columns = [];
        foreach ($crawler->filter('.ea-extensions-column-row') as $row) {
            $row = new Crawler($row);
            $columns[(string) $row->filter('input[type="checkbox"]')->attr('name')] = trim($row->filter('label')->text());
        }

        return $columns;
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
