<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File\CrudExportFileDownloader;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File\CrudExportFileWriter;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class EAExtensionsBundle extends AbstractBundle
{
    protected string $extensionAlias = 'coop_tilleuls_ea_extensions';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('menu_overrides')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('customizable_columns')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('pagination')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('query_parameter')->defaultValue('itemsPerPage')->end()
                        ->integerNode('default_page_size')->defaultValue(25)->end()
                        ->arrayNode('available_page_sizes')
                            ->integerPrototype()->end()
                            ->defaultValue([10, 25, 50, 100])
                        ->end()
                        ->integerNode('max_page_size')->defaultNull()->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $v): bool => $v['enabled'] && !\in_array($v['default_page_size'], $v['available_page_sizes'], true))
                        ->thenInvalid('The "pagination.default_page_size" value must be one of the "pagination.available_page_sizes" values.')
                    ->end()
                ->end()
                ->arrayNode('crud_export')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->integerNode('async_threshold')->defaultValue(1000)->end()
                        ->integerNode('max_limit')->defaultValue(50000)->end()
                        ->scalarNode('storage_service_id')->defaultNull()->end()
                        ->arrayNode('xlsx')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultFalse()->end()
                                ->booleanNode('encryption_enabled')->defaultFalse()->end()
                                ->scalarNode('csv2xlsx_binary')->defaultValue('csv2xlsx')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('doctrine')) {
            return;
        }

        $container->extension('doctrine', [
            'orm' => [
                'mappings' => [
                    'EAExtensionsCustomizableColumns' => [
                        'type' => 'attribute',
                        'dir' => $this->getPath().'/src/CustomizableColumns/Entity',
                        'prefix' => 'CoopTilleuls\\Bundle\\EAExtensionsBundle\\CustomizableColumns\\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($config['menu_overrides']['enabled']) {
            $container->import('../config/menu_overrides_services.php');
        }

        if ($config['customizable_columns']['enabled']) {
            $container->import('../config/services.php');
        }

        $container->import('../config/crud_fields_services.php');

        if ($config['pagination']['enabled']) {
            $builder->setParameter('coop_tilleuls_ea_extensions.pagination.query_parameter', $config['pagination']['query_parameter']);
            $builder->setParameter('coop_tilleuls_ea_extensions.pagination.default_page_size', $config['pagination']['default_page_size']);
            $builder->setParameter('coop_tilleuls_ea_extensions.pagination.available_page_sizes', $config['pagination']['available_page_sizes']);
            $builder->setParameter('coop_tilleuls_ea_extensions.pagination.max_page_size', $config['pagination']['max_page_size']);

            $container->import('../config/pagination_services.php');
        }

        if ($config['crud_export']['enabled']) {
            $builder->setParameter('coop_tilleuls_ea_extensions.crud_export.async_threshold', $config['crud_export']['async_threshold']);
            $builder->setParameter('coop_tilleuls_ea_extensions.crud_export.max_limit', $config['crud_export']['max_limit']);
            $builder->setParameter('coop_tilleuls_ea_extensions.crud_export.xlsx.enabled', $config['crud_export']['xlsx']['enabled']);
            $builder->setParameter('coop_tilleuls_ea_extensions.crud_export.xlsx.encryption_enabled', $config['crud_export']['xlsx']['encryption_enabled']);
            $builder->setParameter('coop_tilleuls_ea_extensions.crud_export.xlsx.csv2xlsx_binary', $config['crud_export']['xlsx']['csv2xlsx_binary']);

            $container->import('../config/crud_export_services.php');

            $storageServiceId = $config['crud_export']['storage_service_id'];
            if (null === $storageServiceId) {
                throw new InvalidConfigurationException('"coop_tilleuls_ea_extensions.crud_export.storage_service_id" must be set when crud_export is enabled.');
            }

            // Bound explicitly (never autowired by type) since a consumer app may have several
            // unrelated League\Flysystem\FilesystemOperator services registered.
            $builder->getDefinition(CrudExportFileWriter::class)->setArgument('$storage', new Reference($storageServiceId));
            $builder->getDefinition(CrudExportFileDownloader::class)->setArgument('$storage', new Reference($storageServiceId));
        }
    }
}
