<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Pagination;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;

/**
 * Optional contract a CrudController can implement to declare the page sizes offered in its
 * selector, independently of its default page size (see DefaultPageSizeProviderInterface).
 */
interface AvailablePageSizesProviderInterface
{
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
