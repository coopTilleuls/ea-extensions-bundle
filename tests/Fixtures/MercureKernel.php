<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use Composer\InstalledVersions;
use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mercure\HubInterface;

/**
 * Same as {@see Kernel}, with "notifications.mercure.enabled" turned on and a
 * {@see RecordingMercureHub} standing in for a real Mercure hub — used to exercise the
 * Mercure-enabled wiring path without requiring a real Mercure hub connection.
 *
 * Also registers the real symfony/mercure-bundle (dev-only — this bundle itself never requires
 * it, only the lower-level symfony/mercure package) purely so the "mercure" Twig function and
 * the subscriber-authorization cookie it can set (Authorization::setCookie(), wired in by
 * MercureBundle's SetCookieSubscriber) are actually available to test — a real host app is
 * expected to install symfony/mercure-bundle itself for the same reason. The dummy "url" below
 * is never actually connected to; only "jwt.secret" is exercised, by the subscriber-cookie tests.
 */
final class MercureKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        yield from parent::registerBundles();
        yield new MercureBundle();
    }

    public function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        parent::configureContainer($container, $loader, $builder);

        $container->extension('coop_tilleuls_ea_extensions', [
            'notifications' => [
                'mercure' => [
                    'enabled' => true,
                ],
            ],
        ]);

        $hub = [
            // Must share a second-level domain with the test client's own host
            // (KernelBrowser defaults to "localhost") — Authorization::setCookie()
            // refuses to set a cookie for a hub it considers a different site.
            'url' => 'https://localhost/.well-known/mercure',
            'jwt' => [
                'secret' => '!TestOnlyMercureHubJWTSecretKey!',
            ],
        ];

        // mercure-bundle 0.6 defaults to the Mercure 1.0 protocol; the subscriber-cookie
        // assertions target the legacy one ("mercureAuthorization" cookie, "mercure" JWT claim).
        if (version_compare((string) InstalledVersions::getVersion('symfony/mercure-bundle'), '0.6.0', '>=')) {
            $hub['protocol_version'] = '0.x';
        }

        $container->extension('mercure', [
            'hubs' => [
                'default' => $hub,
            ],
        ]);

        $services = $container->services();
        $services->set(RecordingMercureHub::class)->public();
        $services->alias(HubInterface::class, RecordingMercureHub::class)->public();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/cache-mercure/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/log-mercure';
    }
}
