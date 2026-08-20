<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Entity\ColumnPreference;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Repository\ColumnPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final class DoctrineColumnPreferenceStorage implements ColumnPreferenceStorageInterface
{
    public function __construct(
        private readonly ColumnPreferenceRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function getColumns(Request $request, string $crudControllerFqcn): ?array
    {
        $ownerId = $this->getOwnerId();
        if (null === $ownerId) {
            return null;
        }

        return $this->repository->findOneByOwnerAndController($ownerId, $crudControllerFqcn)?->getColumns();
    }

    public function saveColumns(Request $request, string $crudControllerFqcn, array $columns): void
    {
        $ownerId = $this->getOwnerId();
        if (null === $ownerId) {
            return;
        }

        $preference = $this->repository->findOneByOwnerAndController($ownerId, $crudControllerFqcn);
        if (null === $preference) {
            $preference = new ColumnPreference($ownerId, $crudControllerFqcn);
            $this->entityManager->persist($preference);
        }

        $preference->setColumns($columns);
        $this->entityManager->flush();
    }

    private function getOwnerId(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }
}
