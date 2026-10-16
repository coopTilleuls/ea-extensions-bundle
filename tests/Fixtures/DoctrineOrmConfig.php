<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use Composer\InstalledVersions;

final class DoctrineOrmConfig
{
    /**
     * doctrine-bundle 3 (Symfony 8) dropped generated proxies in favour of PHP 8.4 native lazy
     * objects and rejects "auto_generate_proxy_classes"; 2.x still needs it.
     *
     * @param array<string, mixed> $orm
     *
     * @return array<string, mixed>
     */
    public static function withProxyOptions(array $orm): array
    {
        if (version_compare((string) InstalledVersions::getVersion('doctrine/doctrine-bundle'), '3.0.0', '<')) {
            $orm['auto_generate_proxy_classes'] = true;
        }

        return $orm;
    }
}
