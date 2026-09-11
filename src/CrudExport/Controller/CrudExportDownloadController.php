<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Controller;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File\CrudExportFileDownloader;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Requester\CrudExportRequesterResolverInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class CrudExportDownloadController
{
    public function __construct(
        private CrudExportFileDownloader $downloader,
        private CrudExportRequesterResolverInterface $requesterResolver,
    ) {
    }

    #[Route('/_ea-extensions/crud-export/download/{requesterId}/{filename}', name: 'ea_extensions_crud_export_download')]
    public function __invoke(string $requesterId, string $filename): StreamedResponse
    {
        if ($requesterId !== $this->requesterResolver->resolveCurrent()) {
            throw new AccessDeniedException('You are not allowed to download this export.');
        }

        return $this->downloader->download(sprintf('%s/%s', $requesterId, $filename), $filename);
    }
}
