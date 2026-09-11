<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Xlsx;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Csv\CsvFileWriter;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Shells out to the `csv2xlsx` binary (https://github.com/mentax/csv2xlsx) to convert
 * a CSV file into an XLSX file. This is an operational prerequisite: the consuming app's
 * server must have the binary installed and on $PATH (or configure its path explicitly
 * via `crud_export.xlsx.csv2xlsx_binary`).
 */
final readonly class CsvToXlsxConverter
{
    public function __construct(private string $csv2xlsxBinary = 'csv2xlsx')
    {
    }

    public function convert(string $csvPath, string $xlsxPath, string $separator = CsvFileWriter::DEFAULT_SEPARATOR): void
    {
        if (!is_file($csvPath)) {
            throw new \RuntimeException(sprintf('CSV file "%s" does not exist.', $csvPath));
        }

        $process = new Process([
            $this->csv2xlsxBinary,
            '--delimiter',
            $separator,
            '--output',
            $xlsxPath,
            $csvPath,
        ]);
        $process->setTimeout(null);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            throw new \RuntimeException(sprintf('csv2xlsx conversion failed: %s', trim($process->getErrorOutput())), 0, $exception);
        }
    }
}
