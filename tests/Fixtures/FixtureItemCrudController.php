<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportColumn;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportConfigurationInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportConfigurationTrait;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Opts in with the trait alone, no override — proves the "Exporter" action and export
 * flow work with CrudExportConfigurationTrait's defaults.
 * The secondary global action is there to test the export button position.
 *
 * @extends AbstractCrudController<FixtureItem>
 */
final class FixtureItemCrudController extends AbstractCrudController implements CrudExportConfigurationInterface
{
    use CrudExportConfigurationTrait;

    public const string SECONDARY_GLOBAL_ACTION = 'fixtureSecondaryGlobalAction';

    public static function getEntityFqcn(): string
    {
        return FixtureItem::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, Action::new(self::SECONDARY_GLOBAL_ACTION)->createAsGlobalAction()->linkToUrl('/fixture-secondary'))
            ->disableAutomaticOrdering();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('name');
        yield IntegerField::new('amount')
            ->setCustomOption(CrudExportColumn::OPTION_VALUE_RESOLVER, FixtureAmountExportResolver::class);
    }
}
