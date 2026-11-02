<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage\ColumnPreferenceStorageInterface;
use Symfony\Component\HttpFoundation\Request;

final class InMemoryColumnPreferenceStorage implements ColumnPreferenceStorageInterface
{
    /**
     * @var array<string, list<string>>
     */
    private array $columnsByController = [];

    public function getColumns(Request $request, string $crudControllerFqcn): ?array
    {
        return $this->columnsByController[$crudControllerFqcn] ?? null;
    }

    public function saveColumns(Request $request, string $crudControllerFqcn, array $columns): void
    {
        $this->columnsByController[$crudControllerFqcn] = $columns;
    }
}
