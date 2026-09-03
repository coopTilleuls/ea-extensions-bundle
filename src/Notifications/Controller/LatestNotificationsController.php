<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Controller;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientResolverInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Repository\NotificationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Renders the "last unread notifications" dashboard card. Embed it in your own dashboard
 * template with `{{ render(controller(LatestNotificationsController::class)) }}`.
 */
final class LatestNotificationsController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationRecipientResolverInterface $recipientResolver,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/_ea-extensions/notifications/latest', name: 'ea_extensions_notifications_latest', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response($this->twig->render('@EAExtensions/notifications/components/_latest_notifications_card.html.twig', [
            'notifications' => $this->notificationRepository->getLastUnread($this->recipientResolver->resolveCurrentRecipient()),
        ]));
    }
}
