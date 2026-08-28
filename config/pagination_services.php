<?php

declare(strict_types=1);

use CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\EasyAdmin\PaginationCrudListener;
use CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\EasyAdmin\PaginationTemplateExtension;
use CoopTilleuls\Bundle\EAExtensionsBundle\Pagination\PageSizeResolver;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    $services->load('CoopTilleuls\\Bundle\\EAExtensionsBundle\\Pagination\\', __DIR__.'/../src/Pagination/')
        ->exclude(__DIR__.'/../src/Pagination/PaginationConfigurationInterface.php')
    ;

    $services->set(PageSizeResolver::class)
        ->args([
            '$queryParameter' => '%coop_tilleuls_ea_extensions.pagination.query_parameter%',
            '$defaultPageSize' => '%coop_tilleuls_ea_extensions.pagination.default_page_size%',
            '$maxPageSize' => '%coop_tilleuls_ea_extensions.pagination.max_page_size%',
        ])
    ;

    $services->set(PaginationCrudListener::class)
        ->arg('$defaultAvailablePageSizes', '%coop_tilleuls_ea_extensions.pagination.available_page_sizes%')
    ;

    $services->set(PaginationTemplateExtension::class)
        ->arg('$queryParameter', '%coop_tilleuls_ea_extensions.pagination.query_parameter%')
    ;
};
