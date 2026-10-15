<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureTabbedCrudController;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class TabNavigationTest extends AbstractFunctionalTestCase
{
    private const string CONTROLLER = 'coop-tilleuls--ea-extensions--tab-navigation';

    public function testEachPaneOfTheNewFormRendersPreviousAndNextButtons(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureTabbedCrudController::class, Action::NEW));

        self::assertResponseIsSuccessful();
        self::assertPanesRenderNavigationButtons($crawler, 3);
    }

    public function testEachPaneOfTheEditFormRendersPreviousAndNextButtons(): void
    {
        $entity = new FixtureEntity();
        $entity->setTitle('Title');
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $crawler = $this->client->request('GET', $this->crudUrl(FixtureTabbedCrudController::class, Action::EDIT, [
            'entityId' => (string) $entity->getId(),
        ]));

        self::assertResponseIsSuccessful();
        self::assertPanesRenderNavigationButtons($crawler, 3);
    }

    public function testTheAppOwnFormThemeKeepsApplying(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'admin', ['ROLE_ADMIN']));

        $this->client->request('GET', $this->crudUrl(FixtureTabbedCrudController::class, Action::NEW));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.fixture-app-form-theme .form-tabs-tablist');
    }

    private static function assertPanesRenderNavigationButtons(Crawler $crawler, int $expectedPaneCount): void
    {
        $tabsContent = $crawler->filter(sprintf('.form-tabs-content[data-controller~="%s"]', self::CONTROLLER));
        self::assertCount(1, $tabsContent);

        $panes = $tabsContent->filter('.tab-content > .tab-pane');
        self::assertCount($expectedPaneCount, $panes);

        // The ea-extensions-tab-navigation-* classes carry the initial (pre-JS) first/last pane
        // state in CSS: they must not get lost.
        $panes->each(static function (Crawler $pane): void {
            self::assertCount(1, $pane->filter(sprintf('.ea-extensions-tab-navigation-buttons[data-%s-target="buttonGroup"]', self::CONTROLLER)));
            self::assertCount(1, $pane->filter(self::buttonSelector('previous', 'previousButton')));
            self::assertCount(1, $pane->filter(self::buttonSelector('next', 'nextButton')));
        });

        self::assertSame('Previous tab', $panes->first()->filter(self::buttonSelector('previous', 'previousButton'))->text());
        self::assertSame('Next tab', $panes->first()->filter(self::buttonSelector('next', 'nextButton'))->text());
    }

    private static function buttonSelector(string $cssSuffix, string $target): string
    {
        return sprintf(
            'button[type="button"].btn.ea-extensions-tab-navigation-%1$s[data-%2$s-target="%3$s"][data-action="%2$s#%1$s"]',
            $cssSuffix,
            self::CONTROLLER,
            $target,
        );
    }
}
