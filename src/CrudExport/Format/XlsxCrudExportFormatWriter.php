<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Format;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportFormat;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportSettings;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Csv\CsvExport;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Password\CrudExportPasswordProviderInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Xlsx\CsvToXlsxConverter;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Xlsx\OoxmlEncryptor;

final readonly class XlsxCrudExportFormatWriter implements CrudExportFormatWriterInterface
{
    public function __construct(
        private CsvExport $csvExport,
        private CsvToXlsxConverter $csvToXlsxConverter,
        private CrudExportSettings $settings,
        private ?CrudExportPasswordProviderInterface $passwordProvider = null,
    ) {
    }

    public static function getFormatKey(): string
    {
        return CrudExportFormat::XLSX;
    }

    public function getFileExtension(): string
    {
        return 'xlsx';
    }

    public function write(array $headers, iterable $rows, string $tmpBasePath, string $crudControllerFqcn, string $requesterId): string
    {
        if (!$this->settings->xlsxEnabled) {
            throw new \RuntimeException('XLSX export was requested but crud_export.xlsx.enabled is off.');
        }

        $csvPath = $tmpBasePath.'.csv';
        $xlsxPath = $tmpBasePath.'.xlsx';

        try {
            $this->csvExport->writeFile($csvPath, $headers, $rows);
            $this->csvToXlsxConverter->convert($csvPath, $xlsxPath);
        } finally {
            if (file_exists($csvPath)) {
                @unlink($csvPath);
            }
        }

        $password = $this->settings->xlsxEncryptionEnabled && null !== $this->passwordProvider
            ? $this->passwordProvider->resolvePassword($crudControllerFqcn, $requesterId)
            : null;

        if (null === $password) {
            return $xlsxPath;
        }

        $encryptedPath = $tmpBasePath.'_encrypted.xlsx';

        try {
            OoxmlEncryptor::encrypt($xlsxPath, $password, $encryptedPath);
        } finally {
            if (file_exists($xlsxPath)) {
                @unlink($xlsxPath);
            }
        }

        return $encryptedPath;
    }
}
