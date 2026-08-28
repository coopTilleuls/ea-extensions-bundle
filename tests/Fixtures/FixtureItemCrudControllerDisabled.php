<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportConfigurationInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportFormat;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportMode;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureItem;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * @extends AbstractCrudController<FixtureItem>
 */
final class FixtureItemCrudControllerDisabled extends AbstractCrudController implements CrudExportConfigurationInterface
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

    public static function isExportEnabled(): bool
    {
        return false;
    }

    public static function getExportPermission(): string|Expression|null
    {
        return null;
    }

    /**
     * @param AdminContext<object> $context
     */
    public static function getExportName(AdminContext $context): ?string
    {
        return null;
    }

    public static function getExportFormat(): string
    {
        return CrudExportFormat::CSV;
    }

    public static function getExportMode(): ?CrudExportMode
    {
        return null;
    }
}
