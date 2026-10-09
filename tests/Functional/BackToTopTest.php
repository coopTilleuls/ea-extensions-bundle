<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudField;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureCrudController;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class BackToTopTest extends AbstractFunctionalTestCase
{
    private const string BUTTON = 'button.ea-extensions-back-to-top[data-controller~="coop-tilleuls--ea-extensions--back-to-top"]';

    public function testCrudPageRendersASingleHiddenButtonWithTheConfiguredThreshold(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureCrudController::class));

        self::assertResponseIsSuccessful();
        $button = $crawler->filter(self::BUTTON);
        self::assertCount(1, $button);
        self::assertStringContainsString('d-none', (string) $button->attr('class'));
        self::assertSame('150', $button->attr('data-coop-tilleuls--ea-extensions--back-to-top-threshold-value'));
        self::assertSame('coop-tilleuls--ea-extensions--back-to-top#scrollToTop', $button->attr('data-action'));
        self::assertSame('Back to top', $button->attr('aria-label'));
    }

    public function testCustomAdminPageRendersTheButton(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $this->client->request('GET', '/admin/custom-page');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, self::BUTTON);
    }

    public function testEmbeddedListingDoesNotRenderASecondButton(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $this->client->request('GET', $this->crudUrl(FixtureCrudController::class, extraParams: [
            CrudField::QUERY_PARAM_CLEAN_LAYOUT => '1',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(self::BUTTON);
    }
}
