<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Event;

/**
 * Dispatched once an async export has definitively failed, i.e. Messenger gives up and
 * no retry is left (see DispatchCrudExportFailedEventListener). Lets a consuming app log
 * the failure or notify the requester.
 */
final readonly class CrudExportFailedEvent
{
    /**
     * @param class-string               $crudControllerFqcn
     * @param array<string, scalar|null> $context            see CrudExportContextProviderInterface
     */
    public function __construct(
        public string $crudControllerFqcn,
        public string $exportName,
        public string $requesterId,
        public \Throwable $exception,
        public array $context,
    ) {
    }
}
