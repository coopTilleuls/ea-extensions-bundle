<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ControllerFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the locked and default columns from the CrudController instance, so they can depend on
 * the current user or request.
 */
final class ColumnsConfigurationResolver
{
    public function __construct(
        private readonly ControllerFactory $controllerFactory,
    ) {
    }

    /**
     * @return array{0: list<string>, 1: list<string>} the locked columns, then the default columns
     */
    public function resolve(string $crudControllerFqcn, Request $request): array
    {
        if (!is_subclass_of($crudControllerFqcn, CustomizableColumnsConfigurationInterface::class)) {
            return [[], []];
        }

        $controller = $this->controllerFactory->getCrudControllerInstance($crudControllerFqcn, Action::INDEX, $request);
        if (!$controller instanceof CustomizableColumnsConfigurationInterface) {
            return [[], []];
        }

        return [$controller->getLockedColumns(), $controller->getDefaultColumns()];
    }
}
