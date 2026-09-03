<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Pagination;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;

/**
 * Optional contract a CrudController can implement to fine-tune the configurable-pagination
 * feature. Implementing it is never required: a controller that doesn't implement it falls
 * back to the bundle-wide config default and page-size list.
 */
interface PaginationConfigurationInterface
{
    /**
     * @param AdminContext<object> $context
     *
     * @return int|null the default page size for this controller, or null to fall back to the
     *                  bundle-wide "pagination.default_page_size" config value
     */
    public static function getDefaultPageSize(AdminContext $context): ?int;

    /**
     * @param AdminContext<object> $context
     *
     * @return list<int>|null the page sizes offered in the selector, null to fall back to the
     *                        bundle-wide "pagination.available_page_sizes" config value, or an
     *                        empty array to lock this controller's page size (the selector is
     *                        hidden and the query-string override is ignored)
     */
    public static function getAvailablePageSizes(AdminContext $context): ?array;
}
