<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Format;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportFormat;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Csv\CsvExport;

final readonly class CsvCrudExportFormatWriter implements CrudExportFormatWriterInterface
{
    public function __construct(private CsvExport $csvExport)
    {
    }

    public static function getFormatKey(): string
    {
        return CrudExportFormat::CSV;
    }

    public function getFileExtension(): string
    {
        return 'csv';
    }

    public function write(array $headers, iterable $rows, string $tmpBasePath, string $crudControllerFqcn, string $requesterId): string
    {
        $path = $tmpBasePath.'.csv';
        $this->csvExport->writeFile($path, $headers, $rows);

        return $path;
    }
}
