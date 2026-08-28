<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Password;

/**
 * Optional hook to password-protect generated XLSX files. No implementation registered
 * (the default) means exports are never encrypted, regardless of `xlsx.encryption_enabled`.
 *
 * Deliberately takes no AdminContext: this is also called from the async message handler,
 * after the original request/context is gone — $requesterId (see CrudExportRequesterResolverInterface)
 * is the only per-request identity that survives the Messenger boundary.
 */
interface CrudExportPasswordProviderInterface
{
    /**
     * @param class-string $crudControllerFqcn
     *
     * Null means "do not encrypt this export"
     */
    public function resolvePassword(string $crudControllerFqcn, string $requesterId): ?string;
}
