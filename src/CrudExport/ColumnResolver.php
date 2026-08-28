<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport;

use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;

/**
 * Turns the fields actually rendered on the index page into the column list to export.
 *
 * Deliberately takes the *processed* FieldCollection of an already-rendered index entity
 * (see CrudExportSubscriber), not a fresh call to configureFields(). This is what makes
 * the optional integration with CustomizableColumns work with zero compile-time coupling:
 * CustomizableColumns' own AfterCrudActionEvent listener mutates that same FieldCollection
 * (removing/reordering columns per the visitor's selection) before this class ever runs, so
 * export naturally reflects "whatever columns the user currently sees" when that feature is
 * present, and simply falls back to the controller's default field set when it isn't.
 */
final class ColumnResolver
{
    /**
     * @return list<CrudExportColumn>
     */
    public function resolve(FieldCollection $fields): array
    {
        $columns = [];

        foreach ($fields as $field) {
            $columns[] = new CrudExportColumn(
                propertyName: $field->getPropertyNameWithSuffix(),
                label: $field->getLabel(),
                resolverFqcn: $field->getCustomOption(CrudExportColumn::OPTION_VALUE_RESOLVER),
            );
        }

        return $columns;
    }
}
