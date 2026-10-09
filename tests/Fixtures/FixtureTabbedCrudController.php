<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureEntity;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * A plain three-tab form with no knowledge of the tab-navigation feature — proves the
 * "Previous tab / Next tab" buttons show up on any tabbed CrudController with zero declarations.
 * Declares its own form theme to prove the bundle's theme gets appended to the app's ones rather
 * than replacing them.
 *
 * @extends AbstractCrudController<FixtureEntity>
 */
final class FixtureTabbedCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FixtureEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setFormThemes(['tab_navigation/app_form_theme.html.twig']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('First');
        yield TextField::new('title');
        yield FormField::addTab('Second');
        yield TextField::new('description');
        yield FormField::addTab('Third');
        yield TextField::new('note');
    }
}
