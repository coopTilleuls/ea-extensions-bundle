<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportColumn;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Deliberately has no knowledge of CrudExport (no interface, no override) — proves the
 * "Exporter" action and export flow work on any plain CrudController out of the box.
 *
 * @extends AbstractCrudController<FixtureItem>
 */
final class FixtureItemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FixtureItem::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('name');
        yield IntegerField::new('amount')
            ->setCustomOption(CrudExportColumn::OPTION_VALUE_RESOLVER, FixtureAmountExportResolver::class);
    }
}
