<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnName;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\CustomizableColumnsConfigurationInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Columns whose property can't identify them. One field is renamed with the column name option,
 * the other is virtual and its property changes on every request.
 *
 * @extends AbstractCrudController<FixtureEntity>
 */
final class FixtureCrudControllerWithColumnNames extends AbstractCrudController implements CustomizableColumnsConfigurationInterface
{
    public const VIRTUAL_PROPERTY_PREFIX = 'virtual_';

    public static function getEntityFqcn(): string
    {
        return FixtureEntity::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('title')->setCustomOption(ColumnName::OPTION, 'heading');
        yield TextField::new(self::VIRTUAL_PROPERTY_PREFIX.bin2hex(random_bytes(4)), 'Computed')
            ->formatValue(static fn (): string => 'computed')
            ->setCustomOption(ColumnName::OPTION, 'computed');
    }

    public function getLockedColumns(): array
    {
        return [];
    }

    public function getDefaultColumns(): array
    {
        return ['id', 'computed'];
    }
}
