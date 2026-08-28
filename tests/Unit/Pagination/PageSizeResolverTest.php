<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\Pagination;

use CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\PageSizeResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PageSizeResolverTest extends TestCase
{
    public function testFallsBackToBundleDefaultWhenNothingElseApplies(): void
    {
        $resolver = new PageSizeResolver('itemsPerPage', 20, null);

        self::assertSame(20, $resolver->resolve(new Request(), null, null));
    }

    public function testControllerDefaultTakesPriorityOverBundleDefault(): void
    {
        $resolver = new PageSizeResolver('itemsPerPage', 20, null);

        self::assertSame(50, $resolver->resolve(new Request(), 50, null));
    }

    public function testQueryOverrideTakesPriorityOverControllerDefault(): void
    {
        $resolver = new PageSizeResolver('itemsPerPage', 20, null);

        self::assertSame(30, $resolver->resolve(new Request(['itemsPerPage' => '30']), 50, null));
    }

    public function testNegativeOrZeroQueryValueFallsBackInsteadOfCrashing(): void
    {
        $resolver = new PageSizeResolver('itemsPerPage', 20, null);

        self::assertSame(20, $resolver->resolve(new Request(['itemsPerPage' => '-1']), null, null));
        self::assertSame(20, $resolver->resolve(new Request(['itemsPerPage' => '0']), null, null));
    }

    public function testNonNumericQueryValueFallsBack(): void
    {
        $resolver = new PageSizeResolver('itemsPerPage', 20, null);

        self::assertSame(20, $resolver->resolve(new Request(['itemsPerPage' => 'not-a-number']), null, null));
    }

    public function testQueryOverrideIsClampedToMaxPageSize(): void
    {
        $resolver = new PageSizeResolver('itemsPerPage', 20, 100);

        self::assertSame(100, $resolver->resolve(new Request(['itemsPerPage' => '100000']), null, null));
    }

    public function testControllerDefaultIsAlsoClampedToMaxPageSize(): void
    {
        $resolver = new PageSizeResolver('itemsPerPage', 20, 100);

        self::assertSame(100, $resolver->resolve(new Request(), 5000, null));
    }

    public function testEmptyAvailablePageSizesLocksTheControllerDefaultAndIgnoresTheQueryOverride(): void
    {
        $resolver = new PageSizeResolver('itemsPerPage', 20, null);

        self::assertSame(10, $resolver->resolve(new Request(['itemsPerPage' => '999']), 10, []));
    }

    public function testEmptyAvailablePageSizesFallsBackToBundleDefaultWithNoControllerDefault(): void
    {
        $resolver = new PageSizeResolver('itemsPerPage', 20, null);

        self::assertSame(20, $resolver->resolve(new Request(['itemsPerPage' => '999']), null, []));
    }
}
