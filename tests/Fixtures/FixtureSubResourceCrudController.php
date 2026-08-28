<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\MenuHighlightAliasInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

/**
 * A CRUD controller with no menu entry of its own (never yielded from
 * {@see FixtureDashboardController::configureMenuItems()}) — declares
 * {@see MenuHighlightAliasInterface} instead, so that visiting any of its pages highlights
 * {@see FixtureCrudController}'s own menu entry rather than nothing.
 *
 * @extends AbstractCrudController<FixtureEntity>
 */
final class FixtureSubResourceCrudController extends AbstractCrudController implements MenuHighlightAliasInterface
{
    public static function getEntityFqcn(): string
    {
        return FixtureEntity::class;
    }

    public static function getMenuHighlightAlias(): string
    {
        return FixtureCrudController::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
    }
}
