<?php

namespace Pixers\DoctrineProfilerBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ProfilerController.
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 */
class ProfilerController implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Renders the profiler panel for the given token.
     *
     * @param string $token The profiler token
     * @param string $id
     *
     * @return Response A Response instance
     */
    public function traceAction(Request $request, $token, $id)
    {
        /** @var $profiler \Symfony\Component\HttpKernel\Profiler\Profiler */
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $profile = $profiler->loadProfile($token);
        $queries = $profile->getCollector('doctrine_profiler')->getQueries();

        if (!isset($queries[$id])) {
            return new Response('This query does not exist.');
        }
        $query = $queries[$id];
        $source = 0;
        foreach ($query['trace'] as $i => $trace) {
            if (isset($trace['query_source'])) {
                $source = $i;
            }
        }

        return $this->container->get('templating')->renderResponse('PixersDoctrineProfilerBundle:Collector:trace.html.twig', array(
            'source' => $source,
            'traces' => $query['trace'],
            'id' => $id,
            'prefix' => $id,
        ));
    }
}
