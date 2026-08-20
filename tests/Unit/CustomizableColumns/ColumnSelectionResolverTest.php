<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\CustomizableColumns;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnSelectionResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage\ColumnPreferenceStorageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ColumnSelectionResolverTest extends TestCase
{
    public function testQueryOverrideTakesPriorityOverStorage(): void
    {
        $storage = $this->createMock(ColumnPreferenceStorageInterface::class);
        $storage->expects(self::never())->method('getColumns');

        $resolver = new ColumnSelectionResolver($storage);

        self::assertSame(
            ['b', 'a'],
            $resolver->resolve(new Request(), 'App\\Foo', 'b,a', ['a', 'b', 'c'], ['a']),
        );
    }

    public function testFallsBackToStorageWhenNoQueryOverride(): void
    {
        $storage = $this->createMock(ColumnPreferenceStorageInterface::class);
        $storage->method('getColumns')->willReturn(['c', 'a']);

        $resolver = new ColumnSelectionResolver($storage);

        self::assertSame(
            ['c', 'a'],
            $resolver->resolve(new Request(), 'App\\Foo', '', ['a', 'b', 'c'], ['a']),
        );
    }

    public function testFallsBackToDefaultColumnsWhenStorageHasNothing(): void
    {
        $storage = $this->createMock(ColumnPreferenceStorageInterface::class);
        $storage->method('getColumns')->willReturn(null);

        $resolver = new ColumnSelectionResolver($storage);

        self::assertSame(
            ['a'],
            $resolver->resolve(new Request(), 'App\\Foo', '', ['a', 'b', 'c'], ['a']),
        );
    }

    public function testUnavailablePropertiesAreFilteredOut(): void
    {
        $storage = $this->createStub(ColumnPreferenceStorageInterface::class);

        $resolver = new ColumnSelectionResolver($storage);

        self::assertSame(
            ['a'],
            $resolver->resolve(new Request(), 'App\\Foo', 'a,not-available', ['a', 'b'], []),
        );
    }

    public function testLockedPropertiesAreAlwaysIncluded(): void
    {
        $storage = $this->createStub(ColumnPreferenceStorageInterface::class);

        $resolver = new ColumnSelectionResolver($storage);

        self::assertSame(
            ['id', 'b'],
            $resolver->resolve(new Request(), 'App\\Foo', 'b', ['id', 'a', 'b'], [], ['id']),
        );
    }
}
