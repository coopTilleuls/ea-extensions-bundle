<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\ColumnSelectionResolver;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Action\ActionsExtensionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CustomizableColumnsActionsExtension implements ActionsExtensionInterface
{
    public const ACTION_NAME = 'customizeColumns';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

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

        $request = $context->getRequest();
        $url = $this->urlGenerator->generate('ea_extensions_customizable_columns_render', [
            'crudControllerFqcn' => $crudControllerFqcn,
            'redirectUrl' => $request->getUri(),
            ColumnSelectionResolver::QUERY_PARAM => $request->query->getString(ColumnSelectionResolver::QUERY_PARAM),
        ]);

        $action = Action::new(self::ACTION_NAME, 'Personnaliser les colonnes')
            ->setIcon('fa fa-columns')
            ->createAsGlobalAction()
            ->linkToUrl($url)
            ->setHtmlAttributes([
                'data-controller' => 'ea-extensions--customizable-columns-modal',
                'data-action' => 'ea-extensions--customizable-columns-modal#open:prevent',
            ]);

        $actions->add(Crud::PAGE_INDEX, $action);
    }
}
