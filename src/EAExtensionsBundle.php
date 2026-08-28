<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
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
    }
}
