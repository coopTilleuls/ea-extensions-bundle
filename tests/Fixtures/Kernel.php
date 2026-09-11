<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\EAExtensionsBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new DoctrineBundle();
        yield new EasyAdminBundle();
        yield new TwigComponentBundle();
        yield new TwigExtraBundle();
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
            'messenger' => [
                'transports' => [
                    'async' => 'in-memory://',
                ],
                'routing' => [
                    'CoopTilleuls\\Bundle\\EAExtensionsBundle\\CrudExport\\Message\\ExportCrudMessage' => 'async',
                ],
            ],
        ]);

        $container->extension('twig', [
            'default_path' => __DIR__.'/templates',
        ]);

        $container->extension('twig_component', [
            'anonymous_template_directory' => 'components',
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'path' => '%kernel.cache_dir%/test.db',
            ],
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'auto_mapping' => false,
                'mappings' => [
                    'EAExtensionsFixtures' => [
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'CoopTilleuls\\Bundle\\EAExtensionsBundle\\Tests\\Fixtures\\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $container->extension('security', [
            'password_hashers' => [
                'Symfony\\Component\\Security\\Core\\User\\InMemoryUser' => 'plaintext',
            ],
            'providers' => [
                'in_memory' => [
                    'memory' => [
                        'users' => [
                            'admin' => ['password' => 'admin', 'roles' => ['ROLE_ADMIN']],
                            'user' => ['password' => 'user', 'roles' => ['ROLE_USER']],
                        ],
                    ],
                ],
            ],
            'firewalls' => [
                'main' => [
                    'provider' => 'in_memory',
                    'logout' => [
                        'path' => '/logout',
                    ],
                ],
            ],
        ]);

        $container->extension('coop_tilleuls_ea_extensions', [
            'crud_export' => [
                'enabled' => true,
                'storage_service_id' => 'fixture.crud_export_storage',
                'async_threshold' => 3,
                'max_limit' => 10,
            ],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(FixtureDashboardController::class)->public();
        $services->set(FixtureCrudController::class)->public();
        $services->set(FixtureSubResourceCrudController::class)->public();
        $services->set(FixtureCrudControllerWithConfiguration::class)->public();
        $services->set(FixtureEmbeddingCrudController::class)->public();
        $services->set(FixtureEmbeddedTargetCrudController::class)->public();
        $services->set(FixturePaginationCrudController::class)->public();
        $services->set(FixtureLockedPaginationCrudController::class)->public();
        $services->set(FixtureMismatchedPaginationCrudController::class)->public();
        $services->set(FixtureDefaultOnlyMismatchedPaginationCrudController::class)->public();
        $services->set(FixtureItemCrudController::class)->public();
        $services->set(FixtureItemCrudControllerWithConfiguration::class)->public();
        $services->set(FixtureItemCrudControllerDisabled::class)->public();
        $services->set(FixtureItemCrudControllerForcedAsync::class)->public();
        $services->set(FixtureItemCrudControllerForcedSync::class)->public();
        $services->set(FixtureAmountExportResolver::class);

        $services->set('fixture.crud_export_storage', Filesystem::class)
            ->args([new Reference('fixture.crud_export_storage.adapter')])
            ->public()
        ;
        $services->set('fixture.crud_export_storage.adapter', LocalFilesystemAdapter::class)
            ->args([sys_get_temp_dir().'/ea-extensions-bundle/crud-export-storage'])
        ;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'easyadmin.routes');
        $routes->import(__DIR__.'/../../src/CustomizableColumns/Controller/', 'attribute');
        $routes->import(__DIR__.'/../../src/CrudExport/Controller/', 'attribute');
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/log';
    }
}
