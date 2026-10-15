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
        $this->processPaginationConfig(['enabled' => true]);

        $this->addToAssertionCount(1);
    }

    public function testMismatchedDefaultPageSizeIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "pagination.default_page_size" value must be one of the "pagination.available_page_sizes" values.');

        $this->processPaginationConfig([
            'enabled' => true,
            'default_page_size' => 30,
            'available_page_sizes' => [10, 25, 50, 100],
        ]);
    }

    public function testMenuOverridesIsDisabledByDefault(): void
    {
        self::assertFalse($this->processDefaultConfig()['menu_overrides']['enabled']);
    }

    public function testCustomizableColumnsIsDisabledByDefault(): void
    {
        self::assertFalse($this->processDefaultConfig()['customizable_columns']['enabled']);
    }

    public function testCrudExportIsDisabledByDefault(): void
    {
        // storage_service_id has no bundle-owned default, so this feature must stay opt-in,
        // or a bare `composer require` fails to boot.
        self::assertFalse($this->processDefaultConfig()['crud_export']['enabled']);
    }

    public function testNotificationsAreDisabledByDefaultWithMercureDisabled(): void
    {
        $config = $this->processNotificationsConfig([]);

        self::assertFalse($config['enabled']);
        self::assertFalse($config['mercure']['enabled']);
    }

    public function testIndexOnlySearchIsDisabledByDefault(): void
    {
        self::assertFalse($this->processDefaultConfig()['index_only_search']['enabled']);
    }

    public function testTabNavigationIsDisabledByDefault(): void
    {
        self::assertFalse($this->processDefaultConfig()['tab_navigation']['enabled']);
    }

    public function testBackToTopIsDisabledByDefaultWithA300pxThreshold(): void
    {
        $config = $this->processDefaultConfig()['back_to_top'];

        self::assertFalse($config['enabled']);
        self::assertSame(300, $config['threshold']);
    }

    public function testNegativeBackToTopThresholdIsRejected(): void
    {
        $bundle = new EAExtensionsBundle();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        $this->expectException(InvalidConfigurationException::class);

        new Processor()->processConfiguration($configuration, [
            ['back_to_top' => ['enabled' => true, 'threshold' => -1]],
        ]);
    }

    public function testMercureCanBeExplicitlyEnabled(): void
    {
        $config = $this->processNotificationsConfig(['mercure' => ['enabled' => true]]);

        self::assertTrue($config['mercure']['enabled']);
    }

    /**
     * @return array<string, mixed>
     */
    private function processDefaultConfig(): array
    {
        $bundle = new EAExtensionsBundle();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $container = new ContainerBuilder();
        $configuration = $extension->getConfiguration([], $container);
        self::assertNotNull($configuration);

        return new Processor()->processConfiguration($configuration, [[]]);
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

    /**
     * @param array<string, mixed> $notificationsConfig
     *
     * @return array<string, mixed>
     */
    private function processNotificationsConfig(array $notificationsConfig): array
    {
        $bundle = new EAExtensionsBundle();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $container = new ContainerBuilder();
        $configuration = $extension->getConfiguration([], $container);
        self::assertNotNull($configuration);

        $processed = new Processor()->processConfiguration($configuration, [
            ['notifications' => $notificationsConfig],
        ]);

        return $processed['notifications'];
    }
}
