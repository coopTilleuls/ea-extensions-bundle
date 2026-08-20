<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Entity;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Repository\ColumnPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ColumnPreferenceRepository::class)]
#[ORM\Table(name: 'ea_extensions_column_preference')]
#[ORM\UniqueConstraint(name: 'ea_extensions_column_preference_owner_controller', columns: ['owner_id', 'controller_fqcn'])]
class ColumnPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'owner_id', type: 'string', length: 255)]
    private string $ownerId;

    #[ORM\Column(name: 'controller_fqcn', type: 'string', length: 255)]
    private string $controllerFqcn;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $columns = [];

    public function __construct(string $ownerId, string $controllerFqcn)
    {
        $this->ownerId = $ownerId;
        $this->controllerFqcn = $controllerFqcn;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnerId(): string
    {
        return $this->ownerId;
    }

    public function getControllerFqcn(): string
    {
        return $this->controllerFqcn;
    }

    /**
     * @return list<string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param list<string> $columns
     */
    public function setColumns(array $columns): void
    {
        $this->columns = $columns;
    }
}
