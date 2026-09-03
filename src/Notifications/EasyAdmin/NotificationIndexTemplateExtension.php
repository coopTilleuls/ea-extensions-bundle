<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the "real" crud/index template (vendor's, or the app's own theme override) that
 * NotificationCrudController::configureCrud() captured before overriding it to its own
 * notifications/crud/index.html.twig — which extends whatever this returns, the same way
 * CrudFieldIndexTemplateExtension does for CrudField's embedded_index.html.twig. Needed
 * because by the time notifications/crud/index.html.twig itself renders, EasyAdmin's
 * ea.templatePath('crud/index') already points back at that very file.
 */
final class NotificationIndexTemplateExtension extends AbstractExtension
{
    public const REQUEST_ATTRIBUTE_PARENT_INDEX_TEMPLATE = '_ea_extensions_notification_parent_index_template';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ea_extensions_notification_crud_index_parent_template', $this->getParentIndexTemplate(...)),
        ];
    }

    public function getParentIndexTemplate(): string
    {
        $template = $this->requestStack->getCurrentRequest()?->attributes->get(self::REQUEST_ATTRIBUTE_PARENT_INDEX_TEMPLATE);

        return \is_string($template) ? $template : '@EasyAdmin/crud/index.html.twig';
    }
}
