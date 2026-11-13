<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns;

use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ControllerFactory;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Resolves the full, permission-filtered candidate column list for the picker UI, by calling
 * the target CrudController's own configureFields(Crud::PAGE_INDEX) directly — the single
 * genuine EasyAdmin extension point this feature relies on.
 */
final class AvailableColumnsResolver
{
    public function __construct(
        private readonly ControllerFactory $controllerFactory,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly CustomizableColumnsFieldsFilter $fieldsFilter,
        private readonly AdminContextFactory $adminContextFactory,
        private readonly AdminControllerRegistryInterface $adminControllers,
    ) {
    }

    /**
     * @return array<string, FieldDto> keyed by column name (see {@see ColumnName}), in configureFields()'s natural order
     */
    public function resolve(string $crudControllerFqcn, Request $request): array
    {
        $controller = $this->controllerFactory->getCrudControllerInstance($crudControllerFqcn, Crud::PAGE_INDEX, $request);
        if (!$controller instanceof CrudControllerInterface) {
            throw new NotFoundHttpException(sprintf('"%s" is not a valid CRUD controller.', $crudControllerFqcn));
        }

        $availableFields = [];
        foreach ($this->configureIndexFields($controller, $request) as $fieldDto) {
            if (!$fieldDto->isDisplayedOn(Crud::PAGE_INDEX)) {
                continue;
            }
            if (!$this->authorizationChecker->isGranted(Permission::EA_VIEW_FIELD, $fieldDto)) {
                continue;
            }
            $availableFields[ColumnName::of($fieldDto)] = $fieldDto;
        }

        return $availableFields;
    }

    /**
     * configureFields() may need EasyAdmin's admin context: AbstractCrudController's own
     * implementation (a controller that doesn't override it) reads the entity from it, and an
     * application's can call $this->getContext(). EasyAdmin only creates that context for its
     * own routes, so it's built here for the picker's ones, and only kept on the request while
     * the fields are configured: the rest of the request isn't an EasyAdmin page.
     *
     * @param CrudControllerInterface<object> $controller
     */
    private function configureIndexFields(CrudControllerInterface $controller, Request $request): FieldCollection
    {
        // Spread inside the callback: a configureFields() generator would otherwise run once the filter is back on
        $configureFields = fn (): FieldCollection => new FieldCollection($this->fieldsFilter->withoutFiltering(
            static fn (): array => [...$controller->configureFields(Crud::PAGE_INDEX)],
        ));

        if ($request->attributes->has(EA::CONTEXT_REQUEST_ATTRIBUTE) || null === $dashboardController = $this->getDashboardController($request)) {
            return $configureFields();
        }

        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $this->adminContextFactory->create($request, $dashboardController, $controller, Crud::PAGE_INDEX));
        try {
            return $configureFields();
        } finally {
            $request->attributes->remove(EA::CONTEXT_REQUEST_ATTRIBUTE);
        }
    }

    /**
     * The dashboard the index page was displayed in (the picker link carries it), or the only
     * dashboard of the application when the link doesn't say.
     */
    private function getDashboardController(Request $request): ?DashboardControllerInterface
    {
        $dashboardControllerFqcn = $request->query->getString(EA::DASHBOARD_CONTROLLER_FQCN);
        if ('' === $dashboardControllerFqcn) {
            if (1 !== $this->adminControllers->getDashboardCount()) {
                return null;
            }
            $dashboardControllerFqcn = (string) $this->adminControllers->getFirstDashboard();
        } elseif (!in_array($dashboardControllerFqcn, $this->adminControllers->getAllDashboards(), true)) {
            throw new NotFoundHttpException(sprintf('"%s" is not a valid dashboard controller.', $dashboardControllerFqcn));
        }

        return $this->controllerFactory->getDashboardControllerInstance($dashboardControllerFqcn, $request);
    }
}
