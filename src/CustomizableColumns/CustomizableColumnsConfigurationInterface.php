<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns;

/**
 * Optional contract a CrudController can implement to fine-tune the customizable-columns
 * feature. Implementing it is never required: a controller that doesn't implement it gets
 * no locked columns and a default column set equal to configureFields()'s natural order.
 *
 * Both methods are called on the controller instance, so they can depend on the current user.
 */
interface CustomizableColumnsConfigurationInterface
{
    /**
     * @return list<string> column names (see {@see ColumnName}) that must always be displayed and cannot be hidden
     */
    public function getLockedColumns(): array;

    /**
     * @return list<string> column names (see {@see ColumnName}), in order, shown by default when the user has no saved preference
     */
    public function getDefaultColumns(): array;
}
