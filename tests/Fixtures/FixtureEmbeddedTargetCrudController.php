<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

/**
 * The CrudField embed target. Deliberately implements NO interface and has NO
 * CrudFields-specific code — proves both the fetch-URL behavior and the generic
 * hideGlobalActions()/hideEntityActions() mechanism work with zero declarations here.
 *
 * @extends AbstractCrudController<FixtureEntity>
 */
final class FixtureEmbeddedTargetCrudController extends AbstractCrudController
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
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
            ->add(TextFilter::new('title'))
        ;
    }
}
