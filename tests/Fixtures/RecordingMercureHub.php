<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

/**
 * A test double recording every {@see Update} it's asked to publish, instead of actually
 * reaching a Mercure hub.
 */
final class RecordingMercureHub implements HubInterface
{
    /**
     * @var list<Update>
     */
    public array $publishedUpdates = [];

    public function getUrl(): string
    {
        return 'https://example.com/.well-known/mercure';
    }

    public function getPublicUrl(): string
    {
        return $this->getUrl();
    }

    public function getProvider(): TokenProviderInterface
    {
        return new StaticTokenProvider('test-token');
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }

    public function publish(Update $update): string
    {
        $this->publishedUpdates[] = $update;

        return 'id-'.\count($this->publishedUpdates);
    }
}
