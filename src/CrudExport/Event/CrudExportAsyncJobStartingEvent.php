<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Event;

/**
 * Dispatched at the very start of the async export message handler, before the export
 * query runs. A Doctrine filter enabled on the original HTTP request (e.g. a
 * soft-deleteable filter turned on by a kernel.request listener) is never automatically
 * re-enabled here — this runs in a Messenger worker, an entirely separate process with
 * no knowledge of that request. Listen to this event to re-enable whatever Doctrine
 * filters (or other per-request state) the export query actually depends on.
 */
final readonly class CrudExportAsyncJobStartingEvent
{
    /**
     * @param class-string $crudControllerFqcn
     */
    public function __construct(
        public string $crudControllerFqcn,
        public string $exportName,
        public string $requesterId,
    ) {
    }
}
