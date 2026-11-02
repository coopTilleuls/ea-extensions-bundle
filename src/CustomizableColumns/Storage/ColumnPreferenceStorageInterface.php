<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnName;
use Symfony\Component\HttpFoundation\Request;

interface ColumnPreferenceStorageInterface
{
    /**
     * @return list<string>|null ordered column names (see {@see ColumnName}), or null when nothing is saved yet
     */
    public function getColumns(Request $request, string $crudControllerFqcn): ?array;

    /**
     * @param list<string> $columns
     */
    public function saveColumns(Request $request, string $crudControllerFqcn, array $columns): void;
}
