<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\CrudExport;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File\CrudExportFileWriter;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Format\CrudExportFormatWriterInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ServiceProviderInterface;

final class TxtFixtureCrudExportFormatWriter implements CrudExportFormatWriterInterface
{
    public static function getFormatKey(): string
    {
        return 'txt';
    }

    public function getFileExtension(): string
    {
        return 'txt';
    }

    public function write(array $headers, iterable $rows, string $tmpBasePath, string $crudControllerFqcn, string $requesterId): string
    {
        $path = $tmpBasePath.'.txt';
        file_put_contents($path, implode("\t", $headers));

        return $path;
    }
}

/**
 * Proves CrudExportFormatWriterInterface is a genuine extension point: a format the bundle
 * doesn't know about (here 'txt') works end-to-end as long as a writer is registered for it,
 * and an unregistered format fails with a clear error instead of silently falling back to CSV.
 */
final class CrudExportFileWriterTest extends TestCase
{
    public function testWriteUsesCustomFormatWriterRegisteredForThatKey(): void
    {
        $storage = $this->createStorage();
        $locator = $this->createLocator(['txt' => new TxtFixtureCrudExportFormatWriter()]);

        $writer = new CrudExportFileWriter($storage, $locator);

        $result = $writer->write(['Name'], [['item-1']], 'user-1', 'export', 'txt', self::class);

        self::assertStringEndsWith('.txt', $result['filename']);
        self::assertTrue($storage->fileExists($result['storagePath']));
        self::assertSame('Name', $storage->read($result['storagePath']));
    }

    public function testWriteThrowsClearErrorForUnregisteredFormat(): void
    {
        $writer = new CrudExportFileWriter($this->createStorage(), $this->createLocator([]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No export format writer registered for format "txt"');

        $writer->write(['Name'], [['item-1']], 'user-1', 'export', 'txt', self::class);
    }

    private function createStorage(): FilesystemOperator
    {
        $dir = sys_get_temp_dir().'/ea-extensions-bundle/crud-export-file-writer-test-'.bin2hex(random_bytes(4));

        return new Filesystem(new LocalFilesystemAdapter($dir));
    }

    /**
     * @param array<string, CrudExportFormatWriterInterface> $writersByKey
     *
     * @return ServiceProviderInterface<CrudExportFormatWriterInterface>
     */
    private function createLocator(array $writersByKey): ServiceProviderInterface
    {
        $locator = $this->createMock(ServiceProviderInterface::class);
        $locator->method('has')->willReturnCallback(static fn (string $key): bool => isset($writersByKey[$key]));
        $locator->method('get')->willReturnCallback(static fn (string $key): CrudExportFormatWriterInterface => $writersByKey[$key]);

        return $locator;
    }
}
