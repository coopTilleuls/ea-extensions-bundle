<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\AutocompletePageSizeKernel;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureAutocompletePaginationCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureCrudController;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class PaginationAutocompletePageSizeTest extends AbstractFunctionalTestCase
{
    use PaginationTestTrait;

    protected static function getKernelClass(): string
    {
        return AutocompletePageSizeKernel::class;
    }

    public function testAutocompleteUsesTheConfiguredPageSizeForAPlainController(): void
    {
        $this->persistFixtureEntities(30);
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $this->client->request('GET', $this->autocompleteUrl(FixtureCrudController::class, ['itemsPerPage' => '2']));

        self::assertResponseIsSuccessful();
        self::assertCount(22, $this->autocompleteResults());
    }

    public function testControllerDefaultWinsOverTheConfiguredAutocompletePageSize(): void
    {
        $this->persistFixtureEntities(30);
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $this->client->request('GET', $this->autocompleteUrl(FixtureAutocompletePaginationCrudController::class));

        self::assertResponseIsSuccessful();
        self::assertCount(30, $this->autocompleteResults());
    }

    public function testTheConfiguredAutocompletePageSizeDoesNotLeakIntoTheIndex(): void
    {
        $this->persistFixtureEntities(30);
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudController::class));

        self::assertResponseIsSuccessful();
        self::assertCount(25, $crawler->filter('tbody tr'));
    }
}
