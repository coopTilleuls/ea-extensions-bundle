<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Subscription;

/**
 * Grants the current subscriber access to the private Mercure topic the popover listens to. The
 * default implementation sets the subscriber authorization cookie itself; override the alias
 * when the host app already builds that cookie from every topic a page subscribes to — symfony/mercure
 * only allows setting it once per request, so a second cookie would fail.
 */
interface NotificationSubscriptionAuthorizerInterface
{
    public function authorize(string $topic): void;
}
