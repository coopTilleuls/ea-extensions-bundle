<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * Default implementation of CrudExportConfigurationInterface: opting a controller into
 * CrudExport takes `implements CrudExportConfigurationInterface` + `use CrudExportConfigurationTrait`,
 * overriding only the methods whose default does not fit.
 *
 * @phpstan-require-implements CrudExportConfigurationInterface
 */
trait CrudExportConfigurationTrait
{
    public static function isExportEnabled(): bool
    {
        return true;
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
