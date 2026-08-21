<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\MenuOverrides;

use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\MenuItemTag;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

final class MenuItemTagTest extends TestCase
{
    public function testNewStoresTheKeyAsARouteParameter(): void
    {
        $menuItem = MenuItem::linkToRoute('Label', null, 'some_route');

        $tagged = MenuItemTag::new($menuItem, 'variant-a');

        self::assertSame($menuItem, $tagged);
        $routeParameters = $menuItem->getAsDto()->getRouteParameters() ?? [];
        self::assertSame('variant-a', $routeParameters[MenuItemTag::QUERY_PARAMETER]);
    }

    public function testApplyToSetsTheParameterOnAnAdminUrlGenerator(): void
    {
        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('set')
            ->with(MenuItemTag::QUERY_PARAMETER, 'variant-a')
            ->willReturnSelf();

        self::assertSame($urlGenerator, MenuItemTag::applyTo($urlGenerator, 'variant-a'));
    }

    public function testAppendToUrlAddsTheParameterToAUrlWithNoQueryString(): void
    {
        self::assertSame(
            '/admin/book?_menuOverrideKey=variant-a',
            MenuItemTag::appendToUrl('/admin/book', 'variant-a')
        );
    }

    public function testAppendToUrlAddsTheParameterToAUrlThatAlreadyHasAQueryString(): void
    {
        self::assertSame(
            '/admin/book?foo=bar&_menuOverrideKey=variant-a',
            MenuItemTag::appendToUrl('/admin/book?foo=bar', 'variant-a')
        );
    }
}
