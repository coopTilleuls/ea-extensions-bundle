<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Controller;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientResolverInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Repository\NotificationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Not a CRUD action: the badge must keep working whichever CRUD controller the "crud_controller"
 * option points to.
 */
final class UnreadNotificationsCountController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationRecipientResolverInterface $recipientResolver,
    ) {
    }

    #[Route('/_ea-extensions/notifications/unread-count', name: 'ea_extensions_notifications_unread_count', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response((string) $this->notificationRepository->getUnreadCount($this->recipientResolver->resolveCurrentRecipients()));
    }
}
