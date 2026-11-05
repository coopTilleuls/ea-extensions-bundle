<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudField;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudFieldLayout;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the "real" crud/index template (vendor's, or the app's own theme override) that
 * CrudFieldLayout::applyIfEmbedded() captured before overriding it — see
 * forced_filters_index.html.twig, which extends whatever this returns so it inherits 100% of
 * the real table/action/pagination markup and only patches the "filters" block.
 *
 * Also answers what an embed has left to show in its header, so embedded_layout.html.twig and
 * embedded_index.html.twig decide from the same place instead of each writing the condition.
 */
final class CrudFieldIndexTemplateExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ea_extensions_crud_field_parent_index_template', $this->getParentIndexTemplate(...)),
            new TwigFunction('ea_extensions_crud_field_has_filters_button', $this->hasFiltersButton(...)),
            new TwigFunction('ea_extensions_crud_field_has_page_actions', $this->hasPageActions(...)),
        ];
    }

    public function getParentIndexTemplate(): string
    {
        $template = $this->requestStack->getCurrentRequest()?->attributes->get(CrudFieldLayout::REQUEST_ATTRIBUTE_PARENT_INDEX_TEMPLATE);

        return \is_string($template) ? $template : '@EasyAdmin/crud/index.html.twig';
    }

    /**
     * The target controller may well have filters configured; the button only belongs to an
     * embed that asked for them through showFilters().
     *
     * @param \Countable|array<mixed> $filters
     */
    public function hasFiltersButton(\Countable|array $filters): bool
    {
        return (bool) $this->requestStack->getCurrentRequest()?->query->getBoolean(CrudField::QUERY_PARAM_SHOW_FILTERS)
            && \count($filters) > 0;
    }

    /**
     * Actions hidden through hideGlobalActions()/hideEntityActions() are already gone from the
     * collections EasyAdmin hands the template, so an embed stripped of everything answers false
     * and its header isn't rendered at all.
     *
     * @param \Countable|array<mixed> $globalActions
     * @param \Countable|array<mixed> $batchActions
     * @param \Countable|array<mixed> $filters
     */
    public function hasPageActions(\Countable|array $globalActions, \Countable|array $batchActions, \Countable|array $filters): bool
    {
        return \count($globalActions) > 0
            || \count($batchActions) > 0
            || $this->hasFiltersButton($filters);
    }
}
