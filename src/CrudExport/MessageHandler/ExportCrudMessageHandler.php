<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\MessageHandler;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Event\CrudExportAsyncJobStartingEvent;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Event\CrudExportFailedEvent;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Event\CrudExportFileReadyEvent;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File\CrudExportGenerator;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Message\ExportCrudMessage;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Normalizer\CrudExportParameterNormalizer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler]
final readonly class ExportCrudMessageHandler
{
    public function __construct(
        private CrudExportParameterNormalizer $normalizer,
        private CrudExportGenerator $generator,
        private UrlGeneratorInterface $urlGenerator,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ExportCrudMessage $message): void
    {
        $this->eventDispatcher->dispatch(new CrudExportAsyncJobStartingEvent(
            crudControllerFqcn: $message->crudControllerFqcn,
            exportName: $message->exportName,
            requesterId: $message->requesterId,
        ));

        $query = $this->normalizer->rebuildQuery($message->dql, $message->parameters);

        try {
            $written = $this->generator->generate(
                query: $query,
                columns: $message->columns,
                crudControllerFqcn: $message->crudControllerFqcn,
                requesterId: $message->requesterId,
                exportName: $message->exportName,
                format: $message->format,
            );
        } catch (\Throwable $exception) {
            $this->eventDispatcher->dispatch(new CrudExportFailedEvent(
                crudControllerFqcn: $message->crudControllerFqcn,
                exportName: $message->exportName,
                requesterId: $message->requesterId,
                exception: $exception,
            ));

            // Messenger's own retry/failure-transport handling still applies —
            // the event above is only a side channel, never a substitute for it.
            throw $exception;
        }

        $downloadUrl = $this->urlGenerator->generate('ea_extensions_crud_export_download', [
            'requesterId' => $message->requesterId,
            'filename' => $written['filename'],
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->eventDispatcher->dispatch(new CrudExportFileReadyEvent(
            crudControllerFqcn: $message->crudControllerFqcn,
            exportName: $message->exportName,
            requesterId: $message->requesterId,
            storagePath: $written['storagePath'],
            filename: $written['filename'],
            async: true,
            downloadUrl: $downloadUrl,
        ));
    }
}
