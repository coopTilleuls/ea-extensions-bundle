<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Csv;

final readonly class CsvFileWriter
{
    public const string DEFAULT_SEPARATOR = ',';

    /**
     * @param array<string>          $headers
     * @param iterable<array<mixed>> $rows
     */
    public function writeFile(
        string $filePath,
        array $headers,
        iterable $rows,
        string $separator = self::DEFAULT_SEPARATOR,
        bool $withBom = false,
    ): void {
        $handle = fopen($filePath, 'wb');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Failed to open file "%s" for writing', $filePath));
        }

        try {
            $this->writeStream($handle, $headers, $rows, $separator, $withBom);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource               $handle
     * @param array<string>          $headers
     * @param iterable<array<mixed>> $rows
     */
    public function writeStream(mixed $handle, array $headers, iterable $rows, string $separator = self::DEFAULT_SEPARATOR, bool $withBom = false): void
    {
        if (!is_resource($handle)) {
            throw new \InvalidArgumentException('Expected a valid stream resource.');
        }

        if ($withBom && false === fwrite($handle, "\xEF\xBB\xBF")) {
            throw new \RuntimeException('Failed to write CSV BOM.');
        }

        $this->writeRow($handle, $headers, $separator);
        foreach ($rows as $row) {
            $this->writeRow($handle, $row, $separator);
        }
    }

    /**
     * @param resource     $handle
     * @param array<mixed> $row
     */
    private function writeRow(mixed $handle, array $row, string $separator): void
    {
        if (false === fputcsv($handle, $row, $separator, '"', '')) {
            throw new \RuntimeException('Failed to write CSV row.');
        }
    }
}
