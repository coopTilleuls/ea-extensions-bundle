<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Pagination;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;

/**
 * Optional contract a CrudController can implement to declare its own default page size,
 * independently of the page sizes offered in the selector (see
 * AvailablePageSizesProviderInterface).
 */
interface DefaultPageSizeProviderInterface
{
    /**
     * @param AdminContext<object> $context
     *
     * @return int|null the default page size for this controller, or null to fall back to the
     *                  bundle-wide "pagination.default_page_size" config value
     */
    public static function getDefaultPageSize(AdminContext $context): ?int;
}
