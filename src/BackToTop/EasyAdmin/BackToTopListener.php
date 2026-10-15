<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\BackToTop\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Twig\Environment;

/**
 * Injects the floating "Back to top" button into every EasyAdmin page (dashboard, CRUD and custom
 * admin routes alike) through EasyAdmin's own "configured_body_contents" layout block — the same
 * mechanism as Assets::addHtmlContentToBody(), so no layout override is needed on the app side.
 *
 * Runs on kernel.controller: the AdminContext (built on kernel.request by EasyAdmin's
 * AdminRouterSubscriber) is ready by then, and nothing has been rendered yet. CrudFields' embedded
 * layout drops "configured_body_contents" altogether, so an embedded listing never adds a second
 * button to its host page.
 */
#[AsEventListener(event: ControllerEvent::class)]
final class BackToTopListener
{
    public const TEMPLATE = '@EAExtensions/back_to_top/button.html.twig';

    public function __construct(
        private readonly Environment $twig,
        private readonly int $threshold,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $context = $event->getRequest()->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE);
        if (!$context instanceof AdminContext) {
            return;
        }

        $context->getAssets()->addHtmlContentToBody($this->twig->render(self::TEMPLATE, [
            'threshold' => $this->threshold,
        ]));
    }
}
