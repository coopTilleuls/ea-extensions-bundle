<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * Optional per-controller fine-tuning for CrudExport. Implementing it is never required:
 * its absence means "same permission as the index page itself, CSV format, name derived
 * from the entity label" — never "export disabled".
 */
interface CrudExportConfigurationInterface
{
    /**
     * False disables the "Exporter" action entirely for this controller — no button,
     * and the export flag is ignored even if requested directly through the URL. Not
     * implementing the interface at all is equivalent to always returning true.
     */
    public static function isExportEnabled(): bool;

    /**
     * Null falls back to the same access rule already enforced by the index page.
     */
    public static function getExportPermission(): string|Expression|null;

    /**
     * @param AdminContext<object> $context current filters/search/sort/entity are available
     *                                      here, so the name can vary accordingly (e.g. include
     *                                      the active filter or the current date)
     *
     * Null falls back to the entity's plural label, lowercased.
     */
    public static function getExportName(AdminContext $context): ?string;

    /**
     * A CrudExportFormat::* constant (CrudExportFormat::CSV is the built-in default),
     * or any other key a registered CrudExportFormatWriterInterface handles — the bundle
     * ships CSV and XLSX writers, a consuming app can add its own for any other format
     * (e.g. 'txt', 'json') by implementing that interface and matching this key against
     * its getFormatKey(). Requesting CrudExportFormat::XLSX has no effect unless the
     * bundle-wide `crud_export.xlsx.enabled` option is turned on.
     */
    public static function getExportFormat(): string;

    /**
     * Null falls back to the row-count-based decision (`crud_export.async_threshold`).
     * Returning CrudExportMode::Sync or ::Async forces that mode regardless of row count —
     * `crud_export.max_limit` still applies as a hard rejection ceiling either way.
     */
    public static function getExportMode(): ?CrudExportMode;
}
