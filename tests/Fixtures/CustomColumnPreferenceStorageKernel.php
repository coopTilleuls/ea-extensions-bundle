<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\CustomizableColumns\Storage\ColumnPreferenceStorageInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\EAExtensionsBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Boots with "customizable_columns.storage_service_id" set, like a host app that stores column
 * preferences itself. The bundle must then leave ColumnPreference unmapped, so the host schema
 * and fixture purger never see its table.
 */
final class CustomColumnPreferenceStorageKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new DoctrineBundle();
        yield new EasyAdminBundle();
        yield new EAExtensionsBundle();
    }

    public function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => [
                'log' => true,
            ],
            'form' => true,
            'csrf_protection' => true,
            'session' => true,
        ]);

        $container->extension('twig', [
            'default_path' => __DIR__.'/templates',
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'path' => '%kernel.cache_dir%/test.db',
            ],
            'orm' => DoctrineOrmConfig::withProxyOptions([
                'auto_mapping' => false,
            ]),
        ]);

        $container->extension('security', [
            'password_hashers' => [
                'Symfony\\Component\\Security\\Core\\User\\InMemoryUser' => 'plaintext',
            ],
            'providers' => [
                'in_memory' => [
                    'memory' => [
                        'users' => [],
                    ],
                ],
            ],
            'firewalls' => [
                'main' => [
                    'provider' => 'in_memory',
                ],
            ],
        ]);

        $container->extension('coop_tilleuls_ea_extensions', [
            'customizable_columns' => [
                'enabled' => true,
                'storage_service_id' => InMemoryColumnPreferenceStorage::class,
            ],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(FixtureDashboardController::class)->public();
        $services->set(InMemoryColumnPreferenceStorage::class);
    }

    public function build(ContainerBuilder $container): void
    {
        // Private aliases are inlined at compile time. This one stays public so the test can fetch it.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->getAlias(ColumnPreferenceStorageInterface::class)->setPublic(true);
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'easyadmin.routes');
        $routes->import(__DIR__.'/../../src/CustomizableColumns/Controller/', 'attribute');
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/cache-custom-column-preference-storage/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/log-custom-column-preference-storage';
    }
}
