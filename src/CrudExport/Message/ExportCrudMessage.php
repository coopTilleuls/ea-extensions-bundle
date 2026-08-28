<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Message;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportColumn;

/**
 * No transport is pinned here (no #[AsMessage]) — routing this message to a transport
 * is left entirely to the consuming app's own Messenger configuration.
 */
final readonly class ExportCrudMessage
{
    /**
     * @param class-string           $crudControllerFqcn
     * @param array<string, mixed>   $parameters         normalized via CrudExportParameterNormalizer
     * @param list<CrudExportColumn> $columns
     */
    public function __construct(
        public string $crudControllerFqcn,
        public string $dql,
        public array $parameters,
        public string $exportName,
        public string $requesterId,
        public array $columns,
        public string $format,
    ) {
    }
}
