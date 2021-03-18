<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $tree_builder = new TreeBuilder('settings');

        $tree_builder->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('access_block')
                    ->arrayPrototype()
                        ->children()
                            ->integerNode('limit')
                                ->defaultValue(5)
                            ->end()
                            ->scalarNode('interval')
                                ->defaultValue('PT6H')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $tree_builder;
    }
}
