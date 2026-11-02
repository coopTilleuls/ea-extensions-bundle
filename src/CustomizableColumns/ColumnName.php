<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;

/**
 * A column's identifier in the query parameter, saved preferences, locked and default columns.
 * It defaults to the field's property. Set the {@see self::OPTION} custom option when the property
 * changes on every request (virtual fields) or when the app already names its columns differently.
 */
final class ColumnName
{
    public const OPTION = 'ea_extensions_column_name';

    public static function of(FieldDto $field): string
    {
        $name = $field->getCustomOption(self::OPTION);

        return \is_string($name) && '' !== $name ? $name : $field->getProperty();
    }
}
