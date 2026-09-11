<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class CrudExportFileDownloader
{
    /**
     * $storage is bound explicitly by EAExtensionsBundle::loadExtension(), see CrudExportFileWriter.
     */
    public function __construct(private FilesystemOperator $storage)
    {
    }

    public function download(string $storagePath, string $filename): StreamedResponse
    {
        if (!$this->storage->fileExists($storagePath)) {
            throw new NotFoundHttpException('Export file not found.');
        }

        $mimeType = $this->storage->mimeType($storagePath);

        $response = new StreamedResponse(function () use ($storagePath): void {
            $stream = $this->storage->readStream($storagePath);
            fpassthru($stream);
            fclose($stream);
        });

        $response->headers->set('Content-Type', $mimeType);
        // $filename ultimately derives from the app-supplied CrudExportConfigurationInterface::
        // getExportName(), which can carry request data — never hand-roll this header from it.
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename));
        $response->headers->set('Content-Length', (string) $this->storage->fileSize($storagePath));

        return $response;
    }
}
