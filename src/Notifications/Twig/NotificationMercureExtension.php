<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Twig;

use Symfony\Component\Mercure\HubInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Only registered when "notifications.mercure.enabled" is true — see
 * config/notifications_mercure_services.php.
 */
final class NotificationMercureExtension extends AbstractExtension
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ea_extensions_notifications_mercure_protocol_version', $this->getProtocolVersion(...)),
        ];
    }

    /**
     * The subscriber's query parameters differ between protocols ("topic"/"lastEventID" for
     * 0.x, "match"/"last_event_id" for 1.0). symfony/mercure < 0.9 only speaks 0.x.
     */
    public function getProtocolVersion(): string
    {
        // @phpstan-ignore function.alreadyNarrowedType (always true on the symfony/mercure version PHPStan runs against, not on the lowest supported one)
        return method_exists(HubInterface::class, 'getProtocolVersion') ? $this->hub->getProtocolVersion()->value : '0.x';
    }
}
