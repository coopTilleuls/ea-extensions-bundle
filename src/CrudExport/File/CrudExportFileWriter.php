<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Format\CrudExportFormatWriterInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @phpstan-type WrittenFile array{storagePath: string, filename: string}
 */
final readonly class CrudExportFileWriter
{
    /**
     * $storage is bound explicitly by EAExtensionsBundle::loadExtension() to whatever
     * service `crud_export.storage_service_id` names — it is never autowired by type,
     * since a consumer app may have several unrelated FilesystemOperator services.
     *
     * @param ServiceProviderInterface<CrudExportFormatWriterInterface> $formatWriterLocator
     */
    public function __construct(
        private FilesystemOperator $storage,
        #[AutowireLocator(CrudExportFormatWriterInterface::class, defaultIndexMethod: 'getFormatKey')]
        private ServiceProviderInterface $formatWriterLocator,
    ) {
    }

    /**
     * @param list<string>          $headers
     * @param iterable<list<mixed>> $rows
     * @param class-string          $crudControllerFqcn
     *
     * @return array{storagePath: string, filename: string}
     */
    public function write(array $headers, iterable $rows, string $requesterId, string $exportName, string $format, string $crudControllerFqcn): array
    {
        if (!$this->formatWriterLocator->has($format)) {
            throw new \RuntimeException(sprintf('No export format writer registered for format "%s". Implement CrudExportFormatWriterInterface with getFormatKey() returning "%s" and register it as a service.', $format, $format));
        }

        /** @var CrudExportFormatWriterInterface $writer */
        $writer = $this->formatWriterLocator->get($format);

        $tmpBase = tempnam(sys_get_temp_dir(), 'crud_export_');
        if (false === $tmpBase) {
            throw new \RuntimeException('Could not create a temporary file.');
        }
        @unlink($tmpBase);

        $localPath = null;

        try {
            $localPath = $writer->write($headers, $rows, $tmpBase, $crudControllerFqcn, $requesterId);

            // $exportName comes from CrudExportConfigurationInterface::getExportName(), which the
            // bundle's own docs encourage building from request data (e.g. the active filter) — it
            // must never be trusted verbatim as a path segment, the same way $requesterId already
            // isn't (see SecurityUserCrudExportRequesterResolver::sanitize()).
            $safeExportName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $exportName) ?? 'export';
            $filename = sprintf('export_%s_%s.%s', $safeExportName, new \DateTimeImmutable()->format('Ymd_His'), $writer->getFileExtension());
            $storagePath = sprintf('%s/%s', $requesterId, $filename);

            $stream = fopen($localPath, 'rb');
            if (false === $stream) {
                throw new \RuntimeException(sprintf('Unable to open generated export file "%s".', $localPath));
            }

            try {
                $this->storage->writeStream($storagePath, $stream);
            } finally {
                fclose($stream);
            }

            return ['storagePath' => $storagePath, 'filename' => $filename];
        } finally {
            if (null !== $localPath && file_exists($localPath)) {
                @unlink($localPath);
            }
        }
    }
}
