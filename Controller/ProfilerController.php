<?php

namespace Pixers\DoctrineProfilerBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;

/**
 * ProfilerController.
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 */
class ProfilerController
{
    /**
     * @var Profiler
     */
    private $profiler;

    /**
     * @var EngineInterface
     */
    private $templating;

    public function __construct(Profiler $profiler, EngineInterface $templating)
    {
        $this->profiler = $profiler;
        $this->templating = $templating;
    }

    /**
     * Renders the profiler panel for the given token.
     *
     * @param string $token The profiler token
     * @param string $id
     *
     * @return Response A Response instance
     */
    public function traceAction($token, $id)
    {
        $this->profiler->disable();
        $profile = $this->profiler->loadProfile($token);
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

        return $this->templating->renderResponse('PixersDoctrineProfilerBundle:Collector:trace.html.twig', array(
            'source' => $source,
            'traces' => $query['trace'],
            'id' => $id,
            'prefix' => $id,
        ));
    }
}
