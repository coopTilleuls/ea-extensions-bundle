<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\MessageHandler;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Event\CrudExportAsyncJobStartingEvent;
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
            context: $message->context,
        ));

        $query = $this->normalizer->rebuildQuery($message->dql, $message->parameters);

        $written = $this->generator->generate(
            query: $query,
            columns: $message->columns,
            crudControllerFqcn: $message->crudControllerFqcn,
            requesterId: $message->requesterId,
            exportName: $message->exportName,
            format: $message->format,
            context: $message->context,
        );

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
            context: $message->context,
        ));
    }
}
