<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\Form\CrudFieldType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Embeds another CrudController's index listing inside the current page (typically a detail
 * page), e.g. a Book's "Reviews" tab showing the review list filtered by that book. Works on
 * any target AbstractCrudController with zero changes required on that controller — including
 * hiding its own global/entity actions, handled generically by CrudFieldActionsExtension.
 */
final class CrudField implements FieldInterface
{
    use FieldTrait;

    public const QUERY_PARAM_CLEAN_LAYOUT = 'cleanLayout';
    public const QUERY_PARAM_HIDE_GLOBAL_ACTIONS = 'hideGlobalActions';
    public const QUERY_PARAM_HIDE_ENTITY_ACTIONS = 'hideEntityActions';
    public const QUERY_PARAM_SHOW_FILTERS = 'showFilters';
    public const QUERY_PARAM_HIDDEN_FILTERS = 'hiddenFilters';

    public const ACTION_SEE_ALL = 'seeAll';

    public const OPTION_LABEL = 'label';
    public const OPTION_CRUD_CONTROLLER = 'crudControllerFqcn';
    public const OPTION_CRUD_CONTROLLER_NAME = 'crudControllerName';
    public const OPTION_OVERRIDE_INDEX_URL = 'overrideIndexUrl';
    public const OPTION_SEE_ALL_URL = 'seeAllUrl';
    public const OPTION_HIDE_GLOBAL_ACTIONS = 'hideGlobalActions';
    public const OPTION_HIDE_ENTITY_ACTIONS = 'hideEntityActions';
    public const OPTION_ADDITIONAL_ACTIONS = 'additionalActions';
    public const OPTION_DISPLAY_AS_CARD = 'displayAsCard';
    public const OPTION_LAZY = 'lazy';
    public const OPTION_SHOW_FILTERS = 'showFilters';
    public const OPTION_CRUD_COLUMNS = 'crudColumns';
    public const OPTION_HELP = 'help';
    public const OPTION_OPEN_ACTIONS_IN_MODAL = 'openActionsInModal';

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return new self()
            ->setProperty('ea_crud_field_'.new Ulid())
            ->setLabel(false)
            ->setTemplatePath('@EAExtensions/crud_fields/field_crud.html.twig')
            ->setFormType(CrudFieldType::class)
            ->setFormTypeOptions(['mapped' => false, 'required' => false])
            ->setCustomOption(self::OPTION_LABEL, $label)
            ->setCustomOption(self::OPTION_HIDE_GLOBAL_ACTIONS, false)
            ->setCustomOption(self::OPTION_HIDE_ENTITY_ACTIONS, false)
            ->setCustomOption(self::OPTION_DISPLAY_AS_CARD, false)
            ->setCustomOption(self::OPTION_ADDITIONAL_ACTIONS, [])
            ->setCustomOption(self::OPTION_LAZY, false)
            ->setCustomOption(self::OPTION_SHOW_FILTERS, false)
            ->setCustomOption(self::OPTION_CRUD_COLUMNS, [])
            ->setCustomOption(self::OPTION_OPEN_ACTIONS_IN_MODAL, false)
        ;
    }

    public function setController(string $crudControllerFqcn): self
    {
        $namespaceParts = explode('\\', $crudControllerFqcn);

        $this->setCustomOption(self::OPTION_CRUD_CONTROLLER, $crudControllerFqcn);
        $this->setCustomOption(self::OPTION_CRUD_CONTROLLER_NAME, array_pop($namespaceParts));

        return $this;
    }

    /**
     * Overrides the embed's fetch URL (e.g. to filter the embedded listing by the current
     * entity). Also becomes the default "voir tout" URL — see setSeeAllUrl() to diverge.
     */
    public function overrideIndexUrl(string $url): self
    {
        $this->setCustomOption(self::OPTION_OVERRIDE_INDEX_URL, $url);

        return $this;
    }

    /**
     * Explicitly sets the "voir tout" link's URL, when it should differ from the fetch URL
     * set via overrideIndexUrl(). Takes precedence over overrideIndexUrl() when both are set.
     */
    public function setSeeAllUrl(string $url): self
    {
        $this->setCustomOption(self::OPTION_SEE_ALL_URL, $url);

        return $this;
    }

    public function addIndexAction(): self
    {
        return $this->addAction(
            Action::new(self::ACTION_SEE_ALL, 'Voir tout')
                ->createAsGlobalAction()
                ->renderAsLink()
                ->asTextLink()
                ->setCssClass('action-see-all')
                ->linkToUrl('')
        );
    }

    public function addAction(?Action $action): self
    {
        if (null === $action) {
            return $this;
        }

        $actions = $this->getAsDto()->getCustomOption(self::OPTION_ADDITIONAL_ACTIONS);
        $actions[] = $action->getAsDto();

        $this->setCustomOption(self::OPTION_ADDITIONAL_ACTIONS, $actions);

        return $this;
    }

    public function hideGlobalActions(bool $hide = true): self
    {
        $this->setCustomOption(self::OPTION_HIDE_GLOBAL_ACTIONS, $hide);

        return $this;
    }

    public function hideEntityActions(bool $hide = true): self
    {
        $this->setCustomOption(self::OPTION_HIDE_ENTITY_ACTIONS, $hide);

        return $this;
    }

    public function displayAsCard(bool $force = true): self
    {
        $this->setCustomOption(self::OPTION_DISPLAY_AS_CARD, $force);

        return $this;
    }

    public function lazy(bool $lazy = true): self
    {
        $this->setCustomOption(self::OPTION_LAZY, $lazy);

        return $this;
    }

    public function showFilters(bool $show = true): self
    {
        $this->setCustomOption(self::OPTION_SHOW_FILTERS, $show);

        return $this;
    }

    /**
     * Opens the embed's own action links (new/edit/custom) in a modal instead of navigating
     * away, submitting them via AJAX and refreshing the embed in place on success. Detail and
     * "voir tout" always navigate normally, and delete keeps using EasyAdmin's own native
     * confirmation-modal flow — neither is affected by this option. Works on any target
     * AbstractCrudController with zero changes required on that controller.
     */
    public function openActionsInModal(bool $open = true): self
    {
        $this->setCustomOption(self::OPTION_OPEN_ACTIONS_IN_MODAL, $open);

        return $this;
    }

    /**
     * @param list<string> $columns
     */
    public function setCrudColumns(array $columns): self
    {
        $this->setCustomOption(self::OPTION_CRUD_COLUMNS, $columns);

        return $this;
    }

    public function setHelp(TranslatableInterface|string $help): self
    {
        $this->setCustomOption(self::OPTION_HELP, $help);

        return $this;
    }
}
