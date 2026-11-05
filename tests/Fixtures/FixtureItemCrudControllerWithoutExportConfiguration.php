<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Deliberately has no knowledge of CrudExport (no interface) — proves export is opt-in:
 * no "Exporter" action, and the export flag is ignored.
 *
 * @extends AbstractCrudController<FixtureItem>
 */
final class FixtureItemCrudControllerWithoutExportConfiguration extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FixtureItem::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('name');
    }
}
