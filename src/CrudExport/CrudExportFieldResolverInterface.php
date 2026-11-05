<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Resolves the exported value of one column for one row, instead of the generic
 * getter/property/relation-path resolution. Declared on a field via:
 *
 *     TextField::new('property')->setCustomOption(CrudExportColumn::OPTION_VALUE_RESOLVER, MyResolver::class)
 */
#[AutoconfigureTag]
interface CrudExportFieldResolverInterface
{
    /**
     * @param array<string, scalar|null> $context captured by CrudExportContextProviderInterface
     *                                            when the export was requested (empty if none is
     *                                            registered) — read it instead of request-scoped
     *                                            services, which are unavailable in an async export
     */
    public function __invoke(object $entity, array $context): mixed;
}
