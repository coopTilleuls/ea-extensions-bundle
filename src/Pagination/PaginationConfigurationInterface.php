<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Pagination;

/**
 * Optional contract a CrudController can implement to fine-tune both the default page size
 * and the selector's page-size list at once. Implementing it is never required: a controller
 * that doesn't implement it falls back to the bundle-wide config default and page-size list.
 * To override only one of the two, implement DefaultPageSizeProviderInterface or
 * AvailablePageSizesProviderInterface alone.
 */
interface PaginationConfigurationInterface extends DefaultPageSizeProviderInterface, AvailablePageSizesProviderInterface
{
}
