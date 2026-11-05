<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudField;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\EmbeddedPageSizeKernel;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureDefaultOnlyPaginationCrudController;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class PaginationEmbeddedPageSizeTest extends AbstractFunctionalTestCase
{
    use PaginationTestTrait;

    protected static function getKernelClass(): string
    {
        return EmbeddedPageSizeKernel::class;
    }

    public function testEmbedUsesTheConfiguredPageSizeForAPlainController(): void
    {
        $this->persistFixtureEntities(30);
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudController::class, extraParams: [
            CrudField::QUERY_PARAM_CLEAN_LAYOUT => '1',
        ]));

        self::assertResponseIsSuccessful();
        self::assertCount(7, $crawler->filter('tbody tr'));
    }

    public function testTheConfiguredEmbeddedPageSizeWinsOverTheControllerDefault(): void
    {
        $this->persistFixtureEntities(30);
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureDefaultOnlyPaginationCrudController::class, extraParams: [
            CrudField::QUERY_PARAM_CLEAN_LAYOUT => '1',
        ]));

        self::assertResponseIsSuccessful();
        self::assertCount(7, $crawler->filter('tbody tr'));
    }

    public function testQueryOverrideWinsOverTheConfiguredEmbeddedPageSize(): void
    {
        $this->persistFixtureEntities(30);
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudController::class, extraParams: [
            CrudField::QUERY_PARAM_CLEAN_LAYOUT => '1',
            'itemsPerPage' => '10',
        ]));

        self::assertResponseIsSuccessful();
        self::assertCount(10, $crawler->filter('tbody tr'));
    }

    public function testTheConfiguredEmbeddedPageSizeDoesNotLeakIntoTheIndex(): void
    {
        $this->persistFixtureEntities(30);
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudController::class));

        self::assertResponseIsSuccessful();
        self::assertCount(25, $crawler->filter('tbody tr'));
    }
}
