<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportFieldResolverInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureItem;

final class FixtureAmountExportResolver implements CrudExportFieldResolverInterface
{
    public function __invoke(object $entity): mixed
    {
        \assert($entity instanceof FixtureItem);

        return sprintf('%.2f', $entity->amount / 100);
    }
}
