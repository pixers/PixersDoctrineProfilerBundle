<?php

namespace Pixers\DoctrineProfilerBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
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
        $connections = $container->getParameter('doctrine.connections');

        foreach ($connections as $name => $serviceId) {
            $backtraceLoggerId = 'doctrine.dbal.logger.backtrace.'.$name;
            $profilingLoggerId = 'doctrine.dbal.logger.profiling.'.$name;
            $chainLoggerId = 'doctrine.dbal.logger.chain.'.$name;

            if (
                !$container->hasDefinition($chainLoggerId)
                || (!$container->hasDefinition($backtraceLoggerId) && !$container->hasDefinition($profilingLoggerId))
            ) {
                // No profiler for this connection
                continue;
            }

            $loggerId = 'pixers_doctrine_profiler.logger.'.$name;

            $loggerDefinition = $this->getLoggerDefinition();
            $container->setDefinition($loggerId, $loggerDefinition);

            $logger = new Reference($loggerId);
            $container->getDefinition($chainLoggerId)
                ->addMethodCall('addLogger', array($logger));

            $container->getDefinition('pixers_doctrine_profiler.data_collector')
                ->addMethodCall('addLogger', array($name, $logger));

            $entityManagerDefinition = $container->getDefinition(sprintf('doctrine.orm.%s_entity_manager', $name));

            $container->getDefinition('doctrine.orm.entity_manager.abstract')
                ->addMethodCall('setLogger', array($logger));
        }
    }

    /**
     * @return ChildDefinition|DefinitionDecorator
     */
    private function getLoggerDefinition()
    {
        if (class_exists(DefinitionDecorator::class)) {
            $childDefinition = new DefinitionDecorator('pixers_doctrine_profiler.logger');
        } else {
            $childDefinition = new ChildDefinition('pixers_doctrine_profiler.logger');
        }

        return $childDefinition;
    }
}
