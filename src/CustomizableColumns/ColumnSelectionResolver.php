<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage\ColumnPreferenceStorageInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the effective, ordered set of index columns for a given CrudController and request,
 * shared by the fields subscriber (applying the resolved set) and the render controller
 * (pre-checking the right checkboxes in the picker).
 */
final class ColumnSelectionResolver
{
    public const QUERY_PARAM = 'eaColumns';

    public function __construct(
        private readonly ColumnPreferenceStorageInterface $storage,
    ) {
    }

    /**
     * @param list<string> $availableProperties properties actually authorized/displayable, in their natural order
     * @param list<string> $defaultProperties   properties shown when nothing is saved/overridden
     * @param list<string> $lockedProperties    properties that are always included, regardless of selection
     *
     * @return list<string>
     */
    public function resolve(Request $request, string $crudControllerFqcn, string $queryOverride, array $availableProperties, array $defaultProperties, array $lockedProperties = []): array
    {
        if ('' !== $queryOverride) {
            $columns = array_values(array_filter(explode(',', $queryOverride), static fn (string $column): bool => '' !== $column));
        } else {
            $columns = $this->storage->getColumns($request, $crudControllerFqcn) ?? $defaultProperties;
        }

        $columns = array_merge($lockedProperties, $columns);

        return array_values(array_intersect(array_unique($columns), $availableProperties));
    }
}
