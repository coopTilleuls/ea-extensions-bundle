<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport;

/**
 * Built-in format keys, returned from CrudExportConfigurationInterface::getExportFormat()
 * and matched against CrudExportFormatWriterInterface::getFormatKey(). A consuming app can
 * register a writer for any other key (e.g. 'txt', 'json') — the format stays a plain
 * string (a service-locator key), it is never a closed set.
 */
final class CrudExportFormat
{
    public const string CSV = 'csv';
    public const string XLSX = 'xlsx';

    private function __construct()
    {
    }
}
