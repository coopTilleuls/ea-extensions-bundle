<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Format;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Extension point for a whole export format (CSV and XLSX are themselves just the two
 * built-in implementations, see CsvCrudExportFormatWriter/XlsxCrudExportFormatWriter).
 * Register a service implementing this interface to add a new format — e.g. 'txt', 'json' —
 * and return that same string from CrudExportConfigurationInterface::getExportFormat() on
 * whichever controller should use it.
 */
#[AutoconfigureTag]
interface CrudExportFormatWriterInterface
{
    /**
     * The format key this writer handles, matched against getExportFormat(). Must be
     * unique across all registered writers.
     */
    public static function getFormatKey(): string;

    /**
     * File extension (without the leading dot) used to name the generated file.
     */
    public function getFileExtension(): string;

    /**
     * @param list<string>          $headers
     * @param iterable<list<mixed>> $rows
     * @param class-string          $crudControllerFqcn
     *
     * Writes the export to a new local temporary file and returns its absolute path.
     * The path must start with $tmpBasePath (append your own suffix/extension to it) so
     * the caller can tell your intermediate files apart if it ever needs to; the caller
     * owns deleting the *returned* file once it's done with it — clean up any other
     * intermediate file yourself.
     */
    public function write(array $headers, iterable $rows, string $tmpBasePath, string $crudControllerFqcn, string $requesterId): string;
}
