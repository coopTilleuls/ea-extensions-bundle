<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\Notifications\Twig;

use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Twig\NotificationMercureExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\ProtocolVersion;

final class NotificationMercureExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function protocolVersions(): iterable
    {
        yield 'legacy' => ['Legacy', '0.x'];
        yield 'mercure 1.0' => ['V1', '1.0'];
    }

    #[DataProvider('protocolVersions')]
    public function testItExposesTheHubProtocolVersion(string $case, string $expected): void
    {
        if (!enum_exists(ProtocolVersion::class)) {
            self::markTestSkipped('symfony/mercure < 0.9 has no protocol version.');
        }

        $hub = $this->createStub(HubInterface::class);
        $hub->method('getProtocolVersion')->willReturn(\constant(ProtocolVersion::class.'::'.$case));

        self::assertSame($expected, new NotificationMercureExtension($hub)->getProtocolVersion());
    }
}
