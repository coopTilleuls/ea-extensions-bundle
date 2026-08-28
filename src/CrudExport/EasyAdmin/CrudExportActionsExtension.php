<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportAction;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportConfigurationInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Action\ActionsExtensionInterface;

/**
 * Adds a global "Exporter" action to every index page, no trait/base class required.
 * Its URL is the current index URL plus one query flag, so every filter/sort/search
 * already applied by the visitor rides along automatically — see CrudExportSubscriber,
 * which reads that flag back off the very same request.
 */
final class CrudExportActionsExtension implements ActionsExtensionInterface
{
    /**
     * @param AdminContext<object> $context
     */
    public function supports(AdminContext $context): bool
    {
        return Crud::PAGE_INDEX === $context->getCrud()?->getCurrentPage();
    }

    /**
     * @param AdminContext<object> $context
     */
    public function extend(Actions $actions, AdminContext $context): void
    {
        $crudControllerFqcn = $context->getCrud()?->getControllerFqcn();
        if (null === $crudControllerFqcn) {
            return;
        }

        $configured = is_subclass_of($crudControllerFqcn, CrudExportConfigurationInterface::class)
            ? $crudControllerFqcn
            : null;

        if (null !== $configured && !$configured::isExportEnabled()) {
            return;
        }

        $request = $context->getRequest();
        $separator = str_contains($request->getUri(), '?') ? '&' : '?';
        $url = $request->getUri().$separator.CrudExportAction::QUERY_PARAM_TRIGGER.'=1';

        $actions->add(Crud::PAGE_INDEX, Action::new(CrudExportAction::ACTION_NAME, 'Exporter')
            ->setIcon('fa fa-download')
            ->createAsGlobalAction()
            ->linkToUrl($url));

        $permission = null !== $configured ? $configured::getExportPermission() : null;
        if (null !== $permission) {
            $actions->setPermission(CrudExportAction::ACTION_NAME, $permission);
        }
    }
}
