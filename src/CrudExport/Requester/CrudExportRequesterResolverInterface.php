<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Requester;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;

/**
 * Resolves an opaque, storage-path-safe identifier for whoever is triggering an export.
 * Used both as the storage sub-directory for the generated file and, on the download
 * route, to check that the current visitor is the one who requested the export.
 */
interface CrudExportRequesterResolverInterface
{
    /**
     * @param AdminContext<object> $context
     */
    public function resolve(AdminContext $context): string;

    /**
     * The current visitor's identifier, resolved outside of an AdminContext
     * (e.g. on the download route or from a Messenger handler).
     */
    public function resolveCurrent(): string;
}
