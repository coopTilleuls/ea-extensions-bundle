<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit;

use CoopTilleuls\Bundle\EAExtensionsBundle\EAExtensionsBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

final class EAExtensionsBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        $this->assertInstanceOf(EAExtensionsBundle::class, new EAExtensionsBundle());
    }

    public function testDefaultPageSizeIsAmongTheDefaultAvailablePageSizes(): void
    {
        $this->processPaginationConfig([]);

        $this->addToAssertionCount(1);
    }

    public function testMismatchedDefaultPageSizeIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "pagination.default_page_size" value must be one of the "pagination.available_page_sizes" values.');

        $this->processPaginationConfig([
            'default_page_size' => 30,
            'available_page_sizes' => [10, 25, 50, 100],
        ]);
    }

    public function testCrudExportIsDisabledByDefault(): void
    {
        // storage_service_id has no bundle-owned default, so this feature — unlike every
        // other one — must stay opt-in, or a bare `composer require` fails to boot.
        $bundle = new EAExtensionsBundle();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $container = new ContainerBuilder();
        $configuration = $extension->getConfiguration([], $container);
        self::assertNotNull($configuration);

        $processed = new Processor()->processConfiguration($configuration, [[]]);

        self::assertFalse($processed['crud_export']['enabled']);
    }

    /**
     * @param array<string, mixed> $paginationConfig
     */
    private function processPaginationConfig(array $paginationConfig): void
    {
        $bundle = new EAExtensionsBundle();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $container = new ContainerBuilder();
        $configuration = $extension->getConfiguration([], $container);
        self::assertNotNull($configuration);

        new Processor()->processConfiguration($configuration, [
            ['pagination' => $paginationConfig],
        ]);
    }
}
