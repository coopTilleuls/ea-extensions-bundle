<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\MenuOverrides\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\EasyAdmin\MenuTagAwareAdminUrlGenerator;
use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\MenuItemTag;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

final class MenuTagAwareAdminUrlGeneratorTest extends TestCase
{
    public function testUnsetAllExceptAlwaysPreservesTheDisambiguationParameter(): void
    {
        $inner = $this->createMock(AdminUrlGeneratorInterface::class);
        $inner->expects(self::once())
            ->method('unsetAllExcept')
            ->with('sort', MenuItemTag::QUERY_PARAMETER)
            ->willReturnSelf();

        $generator = new MenuTagAwareAdminUrlGenerator($inner);

        self::assertSame($generator, $generator->unsetAllExcept('sort'));
    }
}
