<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\CrudExport;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File\CrudExportFileDownloader;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CrudExportFileDownloaderTest extends TestCase
{
    public function testDownloadThrowsWhenFileDoesNotExist(): void
    {
        $downloader = new CrudExportFileDownloader($this->createStorage());

        $this->expectException(NotFoundHttpException::class);

        $downloader->download('nope.csv', 'nope.csv');
    }

    public function testDownloadSetsAProperlyEscapedContentDispositionHeader(): void
    {
        // Regression test: this header used to be hand-built with sprintf('attachment;
        // filename="%s"', $filename) — an unescaped double-quote in $filename (which
        // ultimately derives from app-supplied CrudExportConfigurationInterface::
        // getExportName(), itself sometimes built from request data) could break out of
        // the quoted value and inject extra Content-Disposition parameters.
        $storage = $this->createStorage();
        $storage->write('user-1/export.csv', 'a,b\n1,2\n');

        $filename = 'export_evil"; filename*=UTF-8\'\'x.csv';
        $response = new CrudExportFileDownloader($storage)->download('user-1/export.csv', $filename);

        $header = (string) $response->headers->get('Content-Disposition');

        self::assertStringStartsWith('attachment;', $header);
        // The whole thing must stay ONE quoted "filename" value — the raw quote must come
        // out backslash-escaped, never as a bare `"` that closes the value early and lets
        // the rest be parsed as a second, attacker-controlled header parameter.
        self::assertMatchesRegularExpression('/^attachment; filename="([^"\\\\]|\\\\.)*"$/', $header);
        self::assertStringContainsString('\\"', $header, 'the embedded double-quote must be backslash-escaped');
    }

    private function createStorage(): FilesystemOperator
    {
        $dir = sys_get_temp_dir().'/ea-extensions-bundle/crud-export-file-downloader-test-'.bin2hex(random_bytes(4));

        return new Filesystem(new LocalFilesystemAdapter($dir));
    }
}
