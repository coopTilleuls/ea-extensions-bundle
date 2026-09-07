<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Stands in for a host app's own dashboard layout, which is where
 * notifications/components/_notification_popover.html.twig actually gets rendered in a real
 * app (see notification_bar.html.twig) — needed to exercise its mercure() call (and the
 * subscriber-authorization cookie it sets as a side effect) inside a real HTTP response, which
 * the bundle's own routes never render on their own.
 */
final class FixturePopoverController
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    #[Route('/_fixtures/notification-popover', name: 'fixture_notification_popover', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response($this->twig->render('@EAExtensions/notifications/components/_notification_popover.html.twig'));
    }
}
