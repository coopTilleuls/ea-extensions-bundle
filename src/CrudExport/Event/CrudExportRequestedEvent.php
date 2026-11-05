<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Event;

/**
 * Dispatched once an export has been accepted (permission granted, `max_limit` not
 * exceeded), still within the triggering HTTP request — the security token and current
 * request are available — right before the file is generated (sync) or the job is queued
 * (async). The hook point for an app's own bookkeeping of who exported what (audit log,
 * statistics…); the bundle keeps none itself.
 */
final readonly class CrudExportRequestedEvent
{
    /**
     * @param class-string               $crudControllerFqcn
     * @param array<string, scalar|null> $context            see CrudExportContextProviderInterface
     */
    public function __construct(
        public string $crudControllerFqcn,
        public string $exportName,
        public string $requesterId,
        public int $rowCount,
        public bool $async,
        public array $context,
    ) {
    }
}
