<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Topic;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientInterface;

interface NotificationTopicResolverInterface
{
    public function resolve(NotificationRecipientInterface $recipient): string;
}
