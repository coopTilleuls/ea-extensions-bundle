<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Entity\ColumnPreference;
use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Repository\ColumnPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * Default storage: owner = current Security user's identifier. Named after that resolution
 * strategy (not "Doctrine") because the persistence backend is the uninteresting axis here —
 * almost any implementation will use Doctrine; what actually varies between projects is what
 * determines the "owner" (e.g. cgt-cogitiel keys by "active responsibility" instead of by
 * plain user — a UserResponsabiliteStructureColumnPreferenceStorage there, not this one).
 */
final class SecurityUserColumnPreferenceStorage implements ColumnPreferenceStorageInterface
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

        return $this->repository->findOneByOwner($ownerId)?->getColumnsForController($crudControllerFqcn);
    }

    public function saveColumns(Request $request, string $crudControllerFqcn, array $columns): void
    {
        $ownerId = $this->getOwnerId();
        if (null === $ownerId) {
            return;
        }

        $preference = $this->repository->findOneByOwner($ownerId);
        if (null === $preference) {
            $preference = new ColumnPreference($ownerId);
            $this->entityManager->persist($preference);
        }

        $preference->setColumnsForController($crudControllerFqcn, $columns);
        $this->entityManager->flush();
    }

    private function getOwnerId(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }
}
