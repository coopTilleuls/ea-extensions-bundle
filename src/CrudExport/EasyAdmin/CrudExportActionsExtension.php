<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportAction;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportConfigurationInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportSettings;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Action\ActionsExtensionInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionGroupDto;
use EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Option\ButtonColor;
use EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Option\ButtonVariant;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Adds a global "Exporter" action to the index page of every controller opted in through
 * CrudExportConfigurationInterface (see CrudExportConfigurationTrait for the defaults).
 * Its URL is the current index URL plus one query flag, so every filter/sort/search
 * already applied by the visitor rides along automatically — see CrudExportSubscriber,
 * which reads that flag back off the very same request.
 *
 * The action is always a primary one, rendered to the right of the default (secondary)
 * global actions, whatever the controller's own action ordering.
 */
final class CrudExportActionsExtension implements ActionsExtensionInterface
{
    public function __construct(
        private readonly CrudExportSettings $settings,
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

        $configured = is_subclass_of($crudControllerFqcn, CrudExportConfigurationInterface::class)
            ? $crudControllerFqcn
            : null;

        if (null === $configured || !$configured::isExportEnabled()) {
            return;
        }

        $request = $context->getRequest();
        $separator = str_contains($request->getUri(), '?') ? '&' : '?';
        $url = $request->getUri().$separator.CrudExportAction::QUERY_PARAM_TRIGGER.'=1';

        $actions->add(Crud::PAGE_INDEX, Action::new(CrudExportAction::ACTION_NAME, new TranslatableMessage('crud_export.action_label', domain: 'EAExtensionsBundle'))
            ->setIcon($this->settings->actionIcon)
            ->createAsGlobalAction()
            ->asPrimaryAction()
            ->linkToUrl($url));

        $this->placeAfterDefaultGlobalActions($actions);

        $permission = $configured::getExportPermission();
        if (null !== $permission) {
            $actions->setPermission(CrudExportAction::ACTION_NAME, $permission);
        }
    }

    /**
     * EasyAdmin renders global actions in reverse order of addition, so this action, added after
     * the controller's configureActions(), would end up leftmost. Inserting it before the first
     * default global action renders it right after the default ones. EasyAdmin's automatic
     * ordering would do the same, but any Actions::reorder() call in a controller disables it.
     */
    private function placeAfterDefaultGlobalActions(Actions $actions): void
    {
        $configDto = $actions->getAsDto(null);
        /** @var array<string, ActionDto|ActionGroupDto> $indexActions */
        $indexActions = $configDto->getActions()[Crud::PAGE_INDEX];
        $exportAction = [CrudExportAction::ACTION_NAME => $indexActions[CrudExportAction::ACTION_NAME]];
        unset($indexActions[CrudExportAction::ACTION_NAME]);

        $position = array_search(true, array_map(
            static fn (ActionDto|ActionGroupDto $action): bool => $action instanceof ActionDto && $action->isGlobalAction() && self::hasDefaultColor($action),
            array_values($indexActions),
        ), true);

        $configDto->setActions(Crud::PAGE_INDEX, false === $position
            ? $indexActions + $exportAction
            : array_slice($indexActions, 0, $position, true) + $exportAction + array_slice($indexActions, $position, null, true));
    }

    /**
     * EasyAdmin 5.9 split the color of a button out of its variant (now its style) and
     * deprecated ActionDto::getVariant(); before 5.9, the variant is the color.
     */
    private static function hasDefaultColor(ActionDto $action): bool
    {
        // @phpstan-ignore function.alreadyNarrowedType (always true on the EasyAdmin version PHPStan runs against, not on the lowest supported one)
        if (method_exists($action, 'getColor')) {
            return ButtonColor::Default === $action->getColor();
        }

        return ButtonVariant::Default === $action->getVariant();
    }
}
