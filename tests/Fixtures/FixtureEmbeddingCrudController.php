<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudField;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

/**
 * @extends AbstractCrudController<FixtureEntity>
 */
final class FixtureEmbeddingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return FixtureEntity::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('title');

        if (Crud::PAGE_DETAIL !== $pageName) {
            return;
        }

        yield CrudField::new('embedPlain', 'Plain embed')
            ->setController(FixtureEmbeddedTargetCrudController::class)
        ;

        yield CrudField::new('embedFiltered', 'Filtered embed')
            ->setController(FixtureEmbeddedTargetCrudController::class)
            ->overrideIndexUrl(
                $this->adminUrlGenerator
                    ->setController(FixtureEmbeddedTargetCrudController::class)
                    ->setAction(Crud::PAGE_INDEX)
                    ->set('filters[title]', ['comparison' => '=', 'value' => 'fixture'])
                    ->generateUrl()
            )
            ->addIndexAction()
            ->hideGlobalActions()
            ->hideEntityActions()
            ->displayAsCard()
            ->showFilters()
        ;

        yield CrudField::new('embedLazy', 'Lazy embed')
            ->setController(FixtureEmbeddedTargetCrudController::class)
            ->lazy()
        ;

        yield CrudField::new('embedModalActions', 'Modal actions embed')
            ->setController(FixtureEmbeddedTargetCrudController::class)
            ->openActionsInModal()
        ;
    }
}
