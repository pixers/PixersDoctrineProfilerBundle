<?php

namespace Pixers\DoctrineProfilerBundle;

use Pixers\DoctrineProfilerBundle\DependencyInjection\Compiler\EntityManagersCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PixersDoctrineProfilerBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new EntityManagersCompilerPass());
    }
}
