<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Twig;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientResolverInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Repository\NotificationRepository;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Topic\NotificationTopicResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationRecipientResolverInterface $recipientResolver,
        private readonly NotificationTopicResolverInterface $topicResolver,
        private readonly NotificationRepository $notificationRepository,
        private readonly PropertyAccessorInterface $propertyAccessor,
        #[Autowire(param: 'coop_tilleuls_ea_extensions.notifications.mercure.enabled')]
        private readonly bool $mercureEnabled,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ea_extensions_notification_topic', $this->getCurrentRecipientTopic(...)),
            new TwigFunction('ea_extensions_notifications_mercure_enabled', fn (): bool => $this->mercureEnabled),
            new TwigFunction('ea_extensions_notification_unread_count', $this->getCurrentRecipientUnreadCount(...)),
            new TwigFunction('ea_extensions_access_attribute', fn (object $object, string $attribute): mixed => $this->propertyAccessor->getValue($object, $attribute)),
        ];
    }

    public function getCurrentRecipientUnreadCount(): int
    {
        return $this->notificationRepository->getUnreadCount($this->recipientResolver->resolveCurrentRecipients());
    }

    public function getCurrentRecipientTopic(): ?string
    {
        $recipient = $this->recipientResolver->resolveCurrentRecipient();

        return null === $recipient ? null : $this->topicResolver->resolve($recipient);
    }
}
