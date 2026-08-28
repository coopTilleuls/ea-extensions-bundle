<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudField;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudFieldUrlResolver;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

final class CrudFieldConfigurator implements FieldConfiguratorInterface
{
    public const OPTION_RESOLVED_FETCH_URL = 'resolvedFetchUrl';

    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly CrudFieldUrlResolver $urlResolver,
    ) {
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return CrudField::class === $field->getFieldFqcn();
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $crudControllerFqcn = $field->getCustomOption(CrudField::OPTION_CRUD_CONTROLLER);
        if (null === $crudControllerFqcn) {
            return;
        }

        $bareIndexUrl = $this->adminUrlGenerator
            ->unsetAll()
            ->setController($crudControllerFqcn)
            ->setAction(Action::INDEX)
            ->generateUrl()
        ;

        $overrideIndexUrl = $field->getCustomOption(CrudField::OPTION_OVERRIDE_INDEX_URL);

        $fetchUrl = $this->urlResolver->resolveFetchUrl(
            $overrideIndexUrl ?? $bareIndexUrl,
            (bool) $field->getCustomOption(CrudField::OPTION_HIDE_GLOBAL_ACTIONS),
            (bool) $field->getCustomOption(CrudField::OPTION_HIDE_ENTITY_ACTIONS),
            (bool) $field->getCustomOption(CrudField::OPTION_SHOW_FILTERS),
            $field->getCustomOption(CrudField::OPTION_CRUD_COLUMNS) ?? [],
        );
        $field->setCustomOption(self::OPTION_RESOLVED_FETCH_URL, $fetchUrl);

        $seeAllUrl = $this->urlResolver->resolveSeeAllUrl(
            $field->getCustomOption(CrudField::OPTION_SEE_ALL_URL),
            $overrideIndexUrl,
            $bareIndexUrl,
        );

        $defaultActionTemplatePath = $context->getTemplatePath('crud/action');
        /** @var list<ActionDto> $actions */
        $actions = $field->getCustomOption(CrudField::OPTION_ADDITIONAL_ACTIONS);
        foreach ($actions as $action) {
            if (CrudField::ACTION_SEE_ALL === $action->getName()) {
                $action->setUrl($seeAllUrl);
                $action->setLinkUrl($seeAllUrl);
            }

            $action->setTemplatePath($action->getTemplatePath() ?? $defaultActionTemplatePath);
        }
    }
}
