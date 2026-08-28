<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

/**
 * A genuine CRUD controller (not a plain custom route) — needed so visiting its pages sets the
 * `crudControllerFqcn` request attribute that {@see \CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\EasyAdmin\RecursiveMenuItemMatcher}'s
 * CRUD-index fallback keys off; a plain `#[AdminRoute]` action never triggers that fallback at
 * all, so it can't exercise (or catch a regression in) the disambiguation-vs-fallback interplay.
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
    }
}
