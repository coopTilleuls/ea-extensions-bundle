<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport;

final readonly class CrudExportSettings
{
    public function __construct(
        public int $asyncThreshold,
        public int $maxLimit,
        public bool $xlsxEnabled,
        public bool $xlsxEncryptionEnabled,
        public string $csv2xlsxBinary,
    ) {
    }
}
