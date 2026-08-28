<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\CrudExport;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\ColumnResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportColumn;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\TestCase;

final class ColumnResolverTest extends TestCase
{
    public function testResolveMapsPropertyLabelAndResolver(): void
    {
        $fields = new FieldCollection([
            TextField::new('name', 'Nom'),
            TextField::new('amount')
                ->setCustomOption(CrudExportColumn::OPTION_VALUE_RESOLVER, 'App\\Some\\Resolver'),
        ]);

        $columns = new ColumnResolver()->resolve($fields);

        self::assertCount(2, $columns);

        self::assertSame('name', $columns[0]->propertyName);
        self::assertSame('Nom', $columns[0]->label);
        self::assertNull($columns[0]->resolverFqcn);

        self::assertSame('amount', $columns[1]->propertyName);
        self::assertSame('App\\Some\\Resolver', $columns[1]->resolverFqcn);
    }

    public function testResolveOnEmptyCollectionReturnsEmptyList(): void
    {
        self::assertSame([], new ColumnResolver()->resolve(new FieldCollection([])));
    }
}
