<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use CoopTilleuls\Bundle\EAExtensionsBundle\EAExtensionsBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Boots with "notifications.enabled" turned off and, unlike {@see Kernel}, no
 * resolve_target_entities mapping for NotificationRecipientInterface at all — reproducing a host
 * app that disables the feature and therefore never wires a recipient entity. Regression coverage
 * for EAExtensionsBundle::prependExtension() only mapping the Notification entity when the feature
 * is actually enabled: mapping it unconditionally forced that resolution even for apps that never
 * use the feature.
 */
final class DisabledNotificationsKernel extends BaseKernel
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
            'orm' => [
                'auto_generate_proxy_classes' => true,
                'auto_mapping' => false,
            ],
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
            'notifications' => [
                'enabled' => false,
            ],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(FixtureDashboardController::class)->public();
    }

    public function build(ContainerBuilder $container): void
    {
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'easyadmin.routes');
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/cache-disabled-notifications/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/log-disabled-notifications';
    }
}
