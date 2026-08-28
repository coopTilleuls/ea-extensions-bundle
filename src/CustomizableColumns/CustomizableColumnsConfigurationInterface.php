<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns;

/**
 * Optional contract a CrudController can implement to fine-tune the customizable-columns
 * feature. Implementing it is never required: a controller that doesn't implement it gets
 * no locked columns and a default column set equal to configureFields()'s natural order.
 */
interface CustomizableColumnsConfigurationInterface
{
    /**
     * @return list<string> property names that must always be displayed and cannot be hidden
     */
    public static function getLockedColumns(): array;

    /**
     * @return list<string> property names, in order, shown by default when the user has no saved preference
     */
    public static function getDefaultColumns(): array;
}
