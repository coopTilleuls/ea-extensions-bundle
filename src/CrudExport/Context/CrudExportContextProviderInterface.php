<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Context;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;

/**
 * Optional: reads values from the request (e.g. the user's active profile in session) when the
 * export is requested, so they are still available in an async export, which runs without session.
 * The context is passed to field resolvers and to every CrudExport event. Empty when no
 * implementation is registered.
 */
interface CrudExportContextProviderInterface
{
    /**
     * @param AdminContext<object> $context
     *
     * @return array<string, scalar|null> scalars only: the context must survive the message
     *                                    serialization of whichever transport the app uses
     */
    public function provide(AdminContext $context): array;
}
