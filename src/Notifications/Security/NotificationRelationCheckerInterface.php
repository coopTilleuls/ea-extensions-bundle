<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;

/**
 * Decides whether a notification is "related" to the currently logged-in user, i.e. whether
 * {@see NotificationVoter::IS_RELATED} grants access to it. The bundle only ever depends on this
 * interface — override it (alias it to your own implementation) when your app's access rules go
 * beyond "the notification's recipient is one of my current recipients" (e.g. excluding some
 * roles regardless of a recipient match).
 */
interface NotificationRelationCheckerInterface
{
    public function isRelated(Notification $notification): bool;
}
