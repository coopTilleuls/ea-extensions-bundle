<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Repository;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Entity\ColumnPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ColumnPreference>
 */
class ColumnPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ColumnPreference::class);
    }

    public function findOneByOwnerAndController(string $ownerId, string $controllerFqcn): ?ColumnPreference
    {
        return $this->findOneBy(['ownerId' => $ownerId, 'controllerFqcn' => $controllerFqcn]);
    }
}
