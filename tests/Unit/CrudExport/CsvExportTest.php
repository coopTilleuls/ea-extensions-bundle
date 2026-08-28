<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\CrudExport;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Csv\CsvExport;
use PHPUnit\Framework\TestCase;

final class CsvExportTest extends TestCase
{
    public function testWriteFileWritesHeadersAndRows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_export_test_');
        self::assertIsString($path);

        try {
            new CsvExport()->writeFile($path, ['Name', 'Amount'], [
                ['item-1', 100],
                ['item-2', 200],
            ]);

            $content = (string) file_get_contents($path);
            self::assertSame("Name,Amount\nitem-1,100\nitem-2,200\n", $content);
        } finally {
            @unlink($path);
        }
    }

    public function testWriteFileWithBomPrependsUtf8Bom(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_export_test_');
        self::assertIsString($path);

        try {
            new CsvExport()->writeFile($path, ['Name'], [['item-1']], withBom: true);

            $content = (string) file_get_contents($path);
            self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        } finally {
            @unlink($path);
        }
    }

    public function testWriteFileWithCustomSeparator(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_export_test_');
        self::assertIsString($path);

        try {
            new CsvExport()->writeFile($path, ['a', 'b'], [['1', '2']], separator: ';');

            self::assertSame("a;b\n1;2\n", (string) file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }
}
