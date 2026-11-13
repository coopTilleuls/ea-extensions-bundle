<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/**
 * A CRUD controller that doesn't override configureFields(): EasyAdmin's default implementation
 * guesses the fields from the entity of the admin context, so it only works where one exists.
 *
 * @extends AbstractCrudController<FixtureEntity>
 */
final class FixtureDefaultFieldsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FixtureEntity::class;
    }
}
