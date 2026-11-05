<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Context\CrudExportContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;

/**
 * Reads a query parameter so tests can prove the context is captured from the triggering
 * request, then carried unchanged across the Messenger boundary.
 */
final class FixtureExportContextProvider implements CrudExportContextProviderInterface
{
    public const string QUERY_PARAM = 'tenant';

    public function provide(AdminContext $context): array
    {
        return ['tenant' => $context->getRequest()->query->get(self::QUERY_PARAM)];
    }
}
