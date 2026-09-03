<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Entity;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Repository\ColumnPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One row per owner (not per owner+controller) — the whole column configuration for an owner is
 * a single JSON map keyed by CrudController FQCN.
 */
#[ORM\Entity(repositoryClass: ColumnPreferenceRepository::class)]
#[ORM\Table(name: 'ea_extensions_column_preference')]
#[ORM\UniqueConstraint(name: 'ea_extensions_column_preference_owner', columns: ['owner_id'])]
class ColumnPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'owner_id', type: 'string', length: 255, unique: true)]
    private string $ownerId;

    /**
     * @var array<string, list<string>> CrudController FQCN => ordered property list
     */
    #[ORM\Column(type: 'json')]
    private array $columnsByController = [];

    public function __construct(string $ownerId)
    {
        $this->ownerId = $ownerId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnerId(): string
    {
        return $this->ownerId;
    }

    /**
     * @return list<string>|null
     */
    public function getColumnsForController(string $controllerFqcn): ?array
    {
        return $this->columnsByController[$controllerFqcn] ?? null;
    }

    /**
     * @param list<string> $columns
     */
    public function setColumnsForController(string $controllerFqcn, array $columns): void
    {
        $this->columnsByController[$controllerFqcn] = $columns;
    }
}
