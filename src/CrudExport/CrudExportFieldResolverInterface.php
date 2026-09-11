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
    public function __invoke(object $entity): mixed;
}
