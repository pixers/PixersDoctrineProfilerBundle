<?php

namespace Pixers\DoctrineProfilerBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * EntityManagersCompilerPass.
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 */
class EntityManagersCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $container->getDefinition('doctrine.dbal.logger.chain')
                ->addMethodCall('addLogger', [new Reference('pixers_doctrine_profiler.logger')]);
    }
}
