<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class MenuOverridesTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        self::ensureKernelShutdown();
    }

    public function testDeepMenuIsFullyNestedAndCorrectLeafIsSelected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/fixture?_menuOverrideKey=variant-a');

        self::assertResponseIsSuccessful();

        $crawler = $client->getCrawler();

        $level1 = $this->findMenuItemByLabel($crawler, 'Level 1');
        $level2 = $this->findMenuItemByLabel($crawler, 'Level 2');
        $leafA = $this->findMenuItemByLabel($crawler, 'Leaf A');
        $leafB = $this->findMenuItemByLabel($crawler, 'Leaf B');
        $untaggedSibling = $this->findMenuItemByLabel($crawler, 'Untagged Sibling');

        // the leaf is nested three levels deep: Level 1 > Level 2 > Leaf, not truncated to two
        self::assertStringContainsString('Leaf A', $level2->html());
        self::assertStringContainsString('Level 2', $level1->html());

        // only the leaf whose disambiguation tag matches the visited URL is selected
        self::assertStringContainsString('ea-menu-overrides__item--selected', $this->cssClasses($leafA));
        self::assertStringNotContainsString('ea-menu-overrides__item--selected', $this->cssClasses($leafB));

        // the selection propagates "expanded" through every ancestor, not just the direct parent
        self::assertStringContainsString('ea-menu-overrides__item--expanded', $this->cssClasses($level2));
        self::assertStringContainsString('ea-menu-overrides__item--expanded', $this->cssClasses($level1));

        // regression: an untagged item one level up, pointing to the same destination, must NOT
        // also get selected via EasyAdmin's own CRUD-index fallback once the tagged leaf already
        // matched exactly deeper in the tree
        self::assertStringNotContainsString('ea-menu-overrides__item--selected', $this->cssClasses($untaggedSibling));
    }

    public function testOtherDisambiguatedLeafIsSelectedWhenItsOwnUrlIsVisited(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/fixture?_menuOverrideKey=variant-b');

        self::assertResponseIsSuccessful();

        $crawler = $client->getCrawler();

        $leafA = $this->findMenuItemByLabel($crawler, 'Leaf A');
        $leafB = $this->findMenuItemByLabel($crawler, 'Leaf B');

        self::assertStringContainsString('ea-menu-overrides__item--selected', $this->cssClasses($leafB));
        self::assertStringNotContainsString('ea-menu-overrides__item--selected', $this->cssClasses($leafA));
    }

    public function testTagPropagatesToActionLinksGeneratedOnTheTaggedPage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/fixture?_menuOverrideKey=variant-a');

        self::assertResponseIsSuccessful();

        // EasyAdmin regenerates this "Add new" button's URL from the current page via
        // AdminUrlGeneratorInterface::unsetAllExcept() — MenuTagAwareAdminUrlGenerator must keep
        // the tag through that call, or leaving the exact tagged URL loses the highlight
        $newActionHref = $crawler->filter('a.action-new')->attr('href');
        self::assertNotNull($newActionHref);
        self::assertStringContainsString('_menuOverrideKey=variant-a', $newActionHref);

        $crawler = $client->request('GET', $newActionHref);

        self::assertResponseIsSuccessful();
        $leafA = $this->findMenuItemByLabel($crawler, 'Leaf A');
        self::assertStringContainsString('ea-menu-overrides__item--selected', $this->cssClasses($leafA));
    }

    public function testControllerWithNoMenuEntryHighlightsItsDeclaredAlias(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/fixture-sub-resource');

        self::assertResponseIsSuccessful();

        $crawler = $client->getCrawler();
        $untaggedSibling = $this->findMenuItemByLabel($crawler, 'Untagged Sibling');

        self::assertStringContainsString('ea-menu-overrides__item--selected', $this->cssClasses($untaggedSibling));
    }

    public function testControllerWithNoMenuEntryStillHighlightsItsAliasOnASubPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/fixture-sub-resource/new');

        self::assertResponseIsSuccessful();

        $crawler = $client->getCrawler();
        $untaggedSibling = $this->findMenuItemByLabel($crawler, 'Untagged Sibling');

        self::assertStringContainsString('ea-menu-overrides__item--selected', $this->cssClasses($untaggedSibling));
    }

    private function findMenuItemByLabel(Crawler $crawler, string $label): Crawler
    {
        return $crawler->filter('a')
            ->reduce(static fn (Crawler $node): bool => $label === trim($node->text()))
            ->ancestors()
            ->first();
    }

    private function cssClasses(Crawler $node): string
    {
        return $node->attr('class') ?? '';
    }
}
