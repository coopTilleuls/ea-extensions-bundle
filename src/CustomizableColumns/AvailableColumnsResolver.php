<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns;

use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
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
    ) {
    }

    /**
     * @return array<string, FieldDto> keyed by property name, in configureFields()'s natural order
     */
    public function resolve(string $crudControllerFqcn, Request $request): array
    {
        $controller = $this->controllerFactory->getCrudControllerInstance($crudControllerFqcn, 'index', $request);
        if (!$controller instanceof CrudControllerInterface) {
            throw new NotFoundHttpException(sprintf('"%s" is not a valid CRUD controller.', $crudControllerFqcn));
        }

        $fields = new FieldCollection($controller->configureFields(Crud::PAGE_INDEX));

        $availableFields = [];
        foreach ($fields as $fieldDto) {
            if (!$fieldDto->isDisplayedOn(Crud::PAGE_INDEX)) {
                continue;
            }
            if (!$this->authorizationChecker->isGranted(Permission::EA_VIEW_FIELD, $fieldDto)) {
                continue;
            }
            $availableFields[$fieldDto->getProperty()] = $fieldDto;
        }

        return $availableFields;
    }
}
