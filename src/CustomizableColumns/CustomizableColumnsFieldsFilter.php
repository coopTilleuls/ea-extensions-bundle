<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Keeps only the selected columns among the fields a CrudController returns for its index page.
 *
 * EasyAdmin formats every field returned by configureFields() for every row before
 * {@see EasyAdmin\CustomizableColumnsFieldsSubscriber} can drop the hidden ones, so a hidden
 * association column still loads its relation for each row. Calling this filter from
 * configureFields(Crud::PAGE_INDEX) avoids that work. The column picker turns it off while it
 * lists the available columns.
 */
final class CustomizableColumnsFieldsFilter
{
    private bool $enabled = true;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ColumnSelectionResolver $resolver,
    ) {
    }

    /**
     * @param iterable<FieldInterface|string> $fields
     * @param object                          $controller the CrudController whose configureFields() returned $fields
     *
     * @return list<FieldInterface|string> the selected columns, in the selected order
     */
    public function filter(iterable $fields, object $controller): array
    {
        $fields = array_values([...$fields]);
        $request = $this->requestStack->getCurrentRequest();
        if (!$this->enabled || null === $request || !$controller instanceof CustomizableColumnsConfigurationInterface) {
            return $fields;
        }

        $fieldsByColumn = [];
        foreach ($fields as $field) {
            $fieldsByColumn[\is_string($field) ? $field : ColumnName::of($field->getAsDto())] = $field;
        }
        $availableColumns = array_keys($fieldsByColumn);
        $defaultColumns = $controller->getDefaultColumns();

        $selectedColumns = $this->resolver->resolve(
            $request,
            $controller::class,
            $request->query->getString(ColumnSelectionResolver::QUERY_PARAM),
            $availableColumns,
            [] === $defaultColumns ? $availableColumns : $defaultColumns,
            $controller->getLockedColumns(),
        );

        if ([] === $selectedColumns) {
            return $fields;
        }

        return array_map(static fn (string $column): FieldInterface|string => $fieldsByColumn[$column], $selectedColumns);
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function withoutFiltering(callable $callback): mixed
    {
        $enabled = $this->enabled;
        $this->enabled = false;

        try {
            return $callback();
        } finally {
            $this->enabled = $enabled;
        }
    }
}
