<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Event;

/**
 * Dispatched when an async export fails to generate, right before the exception is
 * rethrown (so Messenger's own retry/failure-transport handling still applies — this
 * is only a side channel for a consuming app to surface/log the failure to the user).
 */
final readonly class CrudExportFailedEvent
{
    /**
     * @param class-string $crudControllerFqcn
     */
    public function __construct(
        public string $crudControllerFqcn,
        public string $exportName,
        public string $requesterId,
        public \Throwable $exception,
    ) {
    }
}
