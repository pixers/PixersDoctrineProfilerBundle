<?php

namespace Pixers\DoctrineProfilerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration.
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $treeBuilder->root('pixers_doctrine_profiler');

        return $treeBuilder;
    }
}
