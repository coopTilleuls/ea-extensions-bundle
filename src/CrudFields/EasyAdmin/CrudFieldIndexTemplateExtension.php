<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\EasyAdmin;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudFields\CrudFieldLayout;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the "real" crud/index template (vendor's, or the app's own theme override) that
 * CrudFieldLayout::applyIfEmbedded() captured before overriding it — see
 * embedded_index.html.twig, which extends whatever this returns so it inherits 100% of the
 * real table/action/pagination markup and only patches the "filters" block.
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
        ];
    }

    public function getParentIndexTemplate(): string
    {
        $template = $this->requestStack->getCurrentRequest()?->attributes->get(CrudFieldLayout::REQUEST_ATTRIBUTE_PARENT_INDEX_TEMPLATE);

        return \is_string($template) ? $template : '@EasyAdmin/crud/index.html.twig';
    }
}
