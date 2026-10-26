<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Same as {@see Kernel}, with "pagination.autocomplete_page_size" set to 22: distinct from both
 * EasyAdmin's own paginator default (20) and the bundle-wide default page size (25).
 */
final class AutocompletePageSizeKernel extends Kernel
{
    public function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        parent::configureContainer($container, $loader, $builder);

        $container->extension('coop_tilleuls_ea_extensions', [
            'pagination' => [
                'autocomplete_page_size' => 22,
            ],
        ]);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/cache-autocomplete-page-size/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ea-extensions-bundle/log-autocomplete-page-size';
    }
}
