<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File\CrudExportFileDownloader;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File\CrudExportFileWriter;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Process;

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
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->integerNode('async_threshold')->defaultValue(1000)->end()
                        ->integerNode('max_limit')->defaultValue(50000)->end()
                        ->scalarNode('storage_service_id')->defaultNull()->end()
                        ->scalarNode('action_icon')->defaultValue('fa fa-download')->end()
                        ->arrayNode('csv')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('separator')->defaultValue(',')->end()
                                ->booleanNode('with_bom')->defaultFalse()->end()
                            ->end()
                        ->end()
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
                ->arrayNode('notifications')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->arrayNode('mercure')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultFalse()->end()
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
                    'EAExtensionsNotifications' => [
                        'type' => 'attribute',
                        'dir' => $this->getPath().'/src/Notifications/Entity',
                        'prefix' => 'CoopTilleuls\\Bundle\\EAExtensionsBundle\\Notifications\\Entity',
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
            // league/flysystem, symfony/messenger and symfony/process are suggested, not
            // required, so apps that never enable this feature don't carry their weight.
            if (!interface_exists(FilesystemOperator::class)) {
                throw new \LogicException('The "coop_tilleuls_ea_extensions.crud_export.enabled" option requires the "league/flysystem" package. Try running "composer require league/flysystem".');
            }

            if (!interface_exists(MessageBusInterface::class)) {
                throw new \LogicException('The "coop_tilleuls_ea_extensions.crud_export.enabled" option requires the "symfony/messenger" package. Try running "composer require symfony/messenger".');
            }

            if (!class_exists(Process::class)) {
                throw new \LogicException('The "coop_tilleuls_ea_extensions.crud_export.enabled" option requires the "symfony/process" package (used for XLSX conversion, even when XLSX itself is disabled). Try running "composer require symfony/process".');
            }

            $builder->setParameter('coop_tilleuls_ea_extensions.crud_export.async_threshold', $config['crud_export']['async_threshold']);
            $builder->setParameter('coop_tilleuls_ea_extensions.crud_export.max_limit', $config['crud_export']['max_limit']);
            $builder->setParameter('coop_tilleuls_ea_extensions.crud_export.action_icon', $config['crud_export']['action_icon']);
            $builder->setParameter('coop_tilleuls_ea_extensions.crud_export.csv.separator', $config['crud_export']['csv']['separator']);
            $builder->setParameter('coop_tilleuls_ea_extensions.crud_export.csv.with_bom', $config['crud_export']['csv']['with_bom']);
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

        if ($config['notifications']['enabled']) {
            $builder->setParameter('coop_tilleuls_ea_extensions.notifications.mercure.enabled', $config['notifications']['mercure']['enabled']);

            $container->import('../config/notifications_services.php');

            if ($config['notifications']['mercure']['enabled']) {
                if (!interface_exists(HubInterface::class)) {
                    throw new \LogicException('The "coop_tilleuls_ea_extensions.notifications.mercure.enabled" option requires the "symfony/mercure" package. Try running "composer require symfony/mercure".');
                }

                $container->import('../config/notifications_mercure_services.php');
            }
        }
    }
}
