<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Event;

/**
 * Dispatched after an export file has been written to storage, for both sync and
 * async exports. This is the hook point for a consuming app's own notification system
 * (e.g. "your export is ready, download it here") — the bundle sends none itself.
 */
final readonly class CrudExportFileReadyEvent
{
    /**
     * @param class-string $crudControllerFqcn
     */
    public function __construct(
        public string $crudControllerFqcn,
        public string $exportName,
        public string $requesterId,
        public string $storagePath,
        public string $filename,
        public bool $async,
        public ?string $downloadUrl,
    ) {
    }
}
