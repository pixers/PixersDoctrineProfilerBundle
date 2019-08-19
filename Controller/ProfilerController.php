<?php

namespace Pixers\DoctrineProfilerBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Twig\Environment;

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
    /** @var Environment */
    private $twig;

    public function __construct(Profiler $profiler, Environment $twig)
    {
        $this->profiler = $profiler;
        $this->twig = $twig;
    }

    /**
     * Renders the profiler panel for the given token.
     *
     * @param string $token The profiler token
     * @param string $id
     */
    public function traceAction($token, $id): Response
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

        return new Response(
            $this->twig
                ->render(
                    'PixersDoctrineProfilerBundle:Collector:trace.html.twig',
                    [
                        'source' => $source,
                        'traces' => $query['trace'],
                        'id' => $id,
                        'prefix' => $id,
                    ]
                )
        );
    }
}
