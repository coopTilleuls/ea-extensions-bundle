<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Subscription;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;

/**
 * Only registered (and its symfony/mercure import only loaded) when "notifications.mercure.enabled"
 * is true — see config/notifications_mercure_services.php.
 */
final class CookieNotificationSubscriptionAuthorizer implements NotificationSubscriptionAuthorizerInterface
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function authorize(string $topic): void
    {
        // The subscriber cookie is only ever attached to the main request's response.
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return;
        }

        $this->authorization->setCookie($request, [$topic]);
    }
}
