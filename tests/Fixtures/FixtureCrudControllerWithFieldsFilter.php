<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\CustomizableColumnsConfigurationInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\CustomizableColumnsFieldsFilter;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Filters its index fields before EasyAdmin formats them. The "note" column counts how many
 * times it gets formatted, so tests can check a hidden column costs nothing.
 *
 * @extends AbstractCrudController<FixtureEntity>
 */
final class FixtureCrudControllerWithFieldsFilter extends AbstractCrudController implements CustomizableColumnsConfigurationInterface
{
    public static int $noteFormatCount = 0;

    public function __construct(
        private readonly CustomizableColumnsFieldsFilter $fieldsFilter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return FixtureEntity::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            IdField::new('id'),
            TextField::new('title'),
            TextField::new('note')->formatValue(static function (?string $value): ?string {
                ++self::$noteFormatCount;

                return $value;
            }),
        ];

        return Crud::PAGE_INDEX === $pageName ? $this->fieldsFilter->filter($fields, $this) : $fields;
    }

    public function getLockedColumns(): array
    {
        return [];
    }

    public function getDefaultColumns(): array
    {
        return ['id', 'title'];
    }
}
