<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudField;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes to templates/pagination/paginator.html.twig the values PaginationCrudListener
 * resolved for the current request, plus the query-parameter name (static config), without
 * the template needing to know about request attributes or reach into another feature's
 * constant directly.
 */
final class PaginationTemplateExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $queryParameter,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ea_extensions_pagination_query_parameter', $this->getQueryParameter(...)),
            new TwigFunction('ea_extensions_pagination_available_page_sizes', $this->getAvailablePageSizes(...)),
            new TwigFunction('ea_extensions_pagination_is_embedded', $this->isEmbedded(...)),
        ];
    }

    public function getQueryParameter(): string
    {
        return $this->queryParameter;
    }

    /**
     * @return list<int>
     */
    public function getAvailablePageSizes(): array
    {
        $sizes = $this->requestStack->getCurrentRequest()?->attributes->get(PaginationCrudListener::REQUEST_ATTRIBUTE_AVAILABLE_PAGE_SIZES);

        return \is_array($sizes) ? array_values($sizes) : [];
    }

    /**
     * The selector hides itself inside a CrudFields embed (same "cleanLayout" query flag) —
     * zero coupling beyond reading that one existing constant, see the "composing two optional
     * features" pattern in this bundle's own dev docs.
     */
    public function isEmbedded(): bool
    {
        return $this->requestStack->getCurrentRequest()?->query->getBoolean(CrudField::QUERY_PARAM_CLEAN_LAYOUT) ?? false;
    }
}
