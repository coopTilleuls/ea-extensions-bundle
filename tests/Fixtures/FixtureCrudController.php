<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Deliberately has NO knowledge of the customizable-columns feature (no interface, no
 * override) — proves the feature works on any plain CrudController out of the box.
 *
 * @extends AbstractCrudController<FixtureEntity>
 */
final class FixtureCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FixtureEntity::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('title');
        yield TextField::new('description');
        yield TextField::new('note');
    }
}
