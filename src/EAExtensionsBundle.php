<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File\CrudExportFileDownloader;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\File\CrudExportFileWriter;
use CoopTilleuls\Bundle\EAExtensionsBundle\MenuOverrides\EasyAdmin\RecursiveMenuItemMatcher;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\MercureBundle\MercureBundle;
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
                        ->booleanNode('enabled')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('customizable_columns')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('pagination')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('query_parameter')->defaultValue('itemsPerPage')->end()
                        ->integerNode('default_page_size')->defaultValue(25)->end()
                        ->arrayNode('available_page_sizes')
                            ->integerPrototype()->end()
                            ->defaultValue([10, 25, 50, 100])
                        ->end()
                        ->integerNode('max_page_size')->defaultNull()->end()
                        ->integerNode('autocomplete_page_size')->defaultNull()->min(1)->end()
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
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->arrayNode('mercure')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->defaultFalse()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('index_only_search')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('tab_navigation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('back_to_top')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->integerNode('threshold')->defaultValue(300)->min(0)->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            // Mapping a feature's entities unconditionally would force apps that disable it to
            // still resolve its interface-typed associations (e.g. resolve_target_entities for
            // Notification's recipient/sender) even though they never use it — so each mapping
            // only gets registered when its own feature is actually enabled.
            $mappings = [];

            if ($this->isFeatureEnabled($builder, 'customizable_columns')) {
                $mappings['EAExtensionsCustomizableColumns'] = [
                    'type' => 'attribute',
                    'dir' => $this->getPath().'/src/CustomizableColumns/Entity',
                    'prefix' => 'CoopTilleuls\\Bundle\\EAExtensionsBundle\\CustomizableColumns\\Entity',
                    'is_bundle' => false,
                ];
            }

            if ($this->isFeatureEnabled($builder, 'notifications')) {
                $mappings['EAExtensionsNotifications'] = [
                    'type' => 'attribute',
                    'dir' => $this->getPath().'/src/Notifications/Entity',
                    'prefix' => 'CoopTilleuls\\Bundle\\EAExtensionsBundle\\Notifications\\Entity',
                    'is_bundle' => false,
                ];
            }

            if ([] !== $mappings) {
                $container->extension('doctrine', ['orm' => ['mappings' => $mappings]]);
            }
        }

        // Needs to be prepended, not just set in loadExtension(): a plain (non-prepend)
        // $container->extension('twig', ...) call there can lose to TwigBundle's own load() if it
        // happens to run first, silently leaving the global undefined.
        if ($builder->hasExtension('twig')) {
            $container->extension('twig', [
                'globals' => [
                    'ea_extensions_notifications_enabled' => $this->isFeatureEnabled($builder, 'notifications'),
                ],
            ]);
        }
    }

    /**
     * Config isn't processed yet at the prepend stage, so this reads the "enabled" flag straight
     * out of the raw (not-yet-merged-with-defaults) config arrays — defaulting to false, matching
     * this feature's own {@see configure()} schema default.
     */
    private function isFeatureEnabled(ContainerBuilder $builder, string $feature): bool
    {
        $enabled = false;
        foreach ($builder->getExtensionConfig($this->extensionAlias) as $config) {
            if (\array_key_exists($feature, $config) && \array_key_exists('enabled', $config[$feature])) {
                $enabled = $config[$feature]['enabled'];
            }
        }

        return $enabled;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($config['menu_overrides']['enabled']) {
            $container->import('../config/menu_overrides_services.php');

            // the page size parameter only exists when pagination is enabled
            if ($config['pagination']['enabled']) {
                $container->services()->get(RecursiveMenuItemMatcher::class)
                    ->arg('$pageSizeQueryParameter', $config['pagination']['query_parameter']);
            }
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
            $builder->setParameter('coop_tilleuls_ea_extensions.pagination.autocomplete_page_size', $config['pagination']['autocomplete_page_size']);

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
                // symfony/mercure and symfony/mercure-bundle are suggested, not required, so apps
                // that never enable this option don't carry their weight.
                if (!interface_exists(HubInterface::class)) {
                    throw new \LogicException('The "coop_tilleuls_ea_extensions.notifications.mercure.enabled" option requires the "symfony/mercure" package. Try running "composer require symfony/mercure".');
                }

                if (!class_exists(MercureBundle::class)) {
                    throw new \LogicException('The "coop_tilleuls_ea_extensions.notifications.mercure.enabled" option requires the "symfony/mercure-bundle" package. Try running "composer require symfony/mercure-bundle".');
                }

                $container->import('../config/notifications_mercure_services.php');
            }
        }

        if ($config['index_only_search']['enabled']) {
            $container->import('../config/index_only_search_services.php');
        }

        if ($config['tab_navigation']['enabled']) {
            $container->import('../config/tab_navigation_services.php');
        }

        if ($config['back_to_top']['enabled']) {
            $builder->setParameter('coop_tilleuls_ea_extensions.back_to_top.threshold', $config['back_to_top']['threshold']);

            $container->import('../config/back_to_top_services.php');
        }
    }
}
