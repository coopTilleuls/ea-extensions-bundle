<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\CrudExport;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportSettings;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Csv\CsvExport;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Format\XlsxCrudExportFormatWriter;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Password\CrudExportPasswordProviderInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Xlsx\CsvToXlsxConverter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the actual decision of whether an export gets password-encrypted — the part
 * that's easy to get backwards (e.g. encrypting whenever a provider exists, instead of
 * only when it actually returns a password). Stubs csv2xlsx with a shell shim instead of
 * requiring the real binary, since only the encryption branching is under test here.
 */
final class XlsxCrudExportFormatWriterTest extends TestCase
{
    private string $csv2xlsxShim;

    protected function setUp(): void
    {
        // Mimics csv2xlsx's CLI contract (--delimiter <c> --output <path> <input>) closely
        // enough for CsvToXlsxConverter, without needing the real binary installed.
        $this->csv2xlsxShim = (string) tempnam(sys_get_temp_dir(), 'csv2xlsx_shim_');
        file_put_contents($this->csv2xlsxShim, <<<'SHIM'
            #!/bin/sh
            out=""
            while [ $# -gt 0 ]; do
                case "$1" in
                    --output) out="$2"; shift 2 ;;
                    --delimiter) shift 2 ;;
                    *) shift ;;
                esac
            done
            printf 'fake-xlsx-content' > "$out"
            SHIM);
        chmod($this->csv2xlsxShim, 0o755);
    }

    protected function tearDown(): void
    {
        @unlink($this->csv2xlsxShim);
    }

    private function createWriter(bool $encryptionEnabled, ?CrudExportPasswordProviderInterface $passwordProvider): XlsxCrudExportFormatWriter
    {
        $settings = new CrudExportSettings(
            asyncThreshold: 1000,
            maxLimit: 50000,
            xlsxEnabled: true,
            xlsxEncryptionEnabled: $encryptionEnabled,
            csv2xlsxBinary: $this->csv2xlsxShim,
        );

        return new XlsxCrudExportFormatWriter(new CsvExport(), new CsvToXlsxConverter($this->csv2xlsxShim), $settings, $passwordProvider);
    }

    private function write(XlsxCrudExportFormatWriter $writer): string
    {
        $path = $writer->write(['id'], [['1']], sys_get_temp_dir().'/xlsx_writer_test_'.uniqid(), self::class, 'requester-1');

        try {
            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    public function testNoEncryptionWhenEncryptionIsDisabledEvenWithAProvider(): void
    {
        $provider = $this->createStub(CrudExportPasswordProviderInterface::class);
        $provider->method('resolvePassword')->willReturn('should-be-ignored');

        $content = $this->write($this->createWriter(encryptionEnabled: false, passwordProvider: $provider));

        self::assertSame('fake-xlsx-content', $content);
    }

    public function testNoEncryptionWhenNoProviderIsRegistered(): void
    {
        $content = $this->write($this->createWriter(encryptionEnabled: true, passwordProvider: null));

        self::assertSame('fake-xlsx-content', $content);
    }

    public function testNoEncryptionWhenTheProviderReturnsNull(): void
    {
        $provider = $this->createStub(CrudExportPasswordProviderInterface::class);
        $provider->method('resolvePassword')->willReturn(null);

        $content = $this->write($this->createWriter(encryptionEnabled: true, passwordProvider: $provider));

        self::assertSame('fake-xlsx-content', $content);
    }

    public function testEncryptsWhenEnabledAndTheProviderReturnsAPassword(): void
    {
        $provider = $this->createStub(CrudExportPasswordProviderInterface::class);
        $provider->method('resolvePassword')->willReturn('S3cr3t!');

        $content = $this->write($this->createWriter(encryptionEnabled: true, passwordProvider: $provider));

        self::assertSame("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", substr($content, 0, 8), 'expected an MS-CFB (encrypted) container, not the raw xlsx bytes');
        self::assertNotSame('fake-xlsx-content', $content);
    }
}
