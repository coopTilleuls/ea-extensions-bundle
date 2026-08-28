<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Action\ActionsExtensionInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;

/**
 * Hides global/entity actions on WHATEVER CrudController is being rendered, driven purely by
 * the hideGlobalActions/hideEntityActions query flags CrudField's fetch URL carries — no
 * interface, no configureActions() change, no declaration whatsoever needed on the target
 * controller. This replaces cgt-cogitiel's per-controller ExpressionLanguage-based opt-in.
 */
final class CrudFieldActionsExtension implements ActionsExtensionInterface
{
    /**
     * @param AdminContext<object> $context
     */
    public function supports(AdminContext $context): bool
    {
        $request = $context->getRequest();

        return $request->query->getBoolean(CrudField::QUERY_PARAM_HIDE_GLOBAL_ACTIONS)
            || $request->query->getBoolean(CrudField::QUERY_PARAM_HIDE_ENTITY_ACTIONS);
    }

    /**
     * @param AdminContext<object> $context
     */
    public function extend(Actions $actions, AdminContext $context): void
    {
        $pageName = $context->getCrud()?->getCurrentPage();
        if (null === $pageName) {
            return;
        }

        $request = $context->getRequest();
        $hideGlobalActions = $request->query->getBoolean(CrudField::QUERY_PARAM_HIDE_GLOBAL_ACTIONS);
        $hideEntityActions = $request->query->getBoolean(CrudField::QUERY_PARAM_HIDE_ENTITY_ACTIONS);

        foreach ($actions->getAsDto($pageName)->getActions() as $name => $action) {
            if (!$action instanceof ActionDto) {
                continue;
            }

            if ($hideGlobalActions && ($action->isGlobalAction() || $action->isBatchAction())) {
                $actions->disable($name);
            }

            if ($hideEntityActions && $action->isEntityAction()) {
                $actions->disable($name);
            }
        }
    }
}
