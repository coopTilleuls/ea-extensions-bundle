<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\CrudFields;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudFieldUrlResolver;
use PHPUnit\Framework\TestCase;

final class CrudFieldUrlResolverTest extends TestCase
{
    public function testResolveFetchUrlAlwaysAppendsCleanLayout(): void
    {
        $resolver = new CrudFieldUrlResolver();

        self::assertSame('/index?cleanLayout=1', $resolver->resolveFetchUrl('/index', false, false, false));
    }

    public function testResolveFetchUrlAppendsHideFlagsOnlyWhenSet(): void
    {
        $resolver = new CrudFieldUrlResolver();

        $url = $resolver->resolveFetchUrl('/index', true, true, false);

        self::assertStringContainsString('hideGlobalActions=1', $url);
        self::assertStringContainsString('hideEntityActions=1', $url);
    }

    public function testResolveFetchUrlOmitsHideFlagsWhenNotSet(): void
    {
        $resolver = new CrudFieldUrlResolver();

        $url = $resolver->resolveFetchUrl('/index', false, false, false);

        self::assertStringNotContainsString('hideGlobalActions', $url);
        self::assertStringNotContainsString('hideEntityActions', $url);
    }

    public function testResolveFetchUrlAppendsCrudColumns(): void
    {
        $resolver = new CrudFieldUrlResolver();

        $url = $resolver->resolveFetchUrl('/index', false, false, false, ['id', 'title']);

        self::assertStringContainsString('eaColumns=id%2Ctitle', $url);
    }

    public function testResolveFetchUrlMarksExistingFiltersAsHiddenWhenShowingFilters(): void
    {
        $resolver = new CrudFieldUrlResolver();

        $url = $resolver->resolveFetchUrl('/index?filters%5Bbook%5D=5', false, false, true);

        self::assertStringContainsString('showFilters=1', $url);
        self::assertStringContainsString('hiddenFilters%5B0%5D=book', $url);
    }

    public function testResolveSeeAllUrlDefaultsToOverrideIndexUrlNotBareIndex(): void
    {
        $resolver = new CrudFieldUrlResolver();

        self::assertSame('/filtered', $resolver->resolveSeeAllUrl(null, '/filtered', '/bare'));
    }

    public function testResolveSeeAllUrlFallsBackToBareIndexWhenNoOverride(): void
    {
        $resolver = new CrudFieldUrlResolver();

        self::assertSame('/bare', $resolver->resolveSeeAllUrl(null, null, '/bare'));
    }

    public function testExplicitSeeAllUrlTakesPrecedenceOverEverything(): void
    {
        $resolver = new CrudFieldUrlResolver();

        self::assertSame('/explicit', $resolver->resolveSeeAllUrl('/explicit', '/filtered', '/bare'));
    }
}
