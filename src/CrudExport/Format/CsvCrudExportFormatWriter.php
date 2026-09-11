<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Format;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportFormat;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportSettings;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Csv\CsvFileWriter;

final readonly class CsvCrudExportFormatWriter implements CrudExportFormatWriterInterface
{
    public function __construct(
        private CsvFileWriter $csvFileWriter,
        private CrudExportSettings $settings,
    ) {
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
        $this->csvFileWriter->writeFile($path, $headers, $rows, $this->settings->csvSeparator, $this->settings->csvWithBom);

        return $path;
    }
}
