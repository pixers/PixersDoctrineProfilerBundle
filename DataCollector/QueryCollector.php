<?php

namespace Pixers\DoctrineProfilerBundle\DataCollector;

use Exception;
use Pixers\DoctrineProfilerBundle\Logging\StackTraceLogger;
use Pixers\DoctrineProfilerBundle\Stacktrace\FlattenTraceGraphIterator;
use Pixers\DoctrineProfilerBundle\Stacktrace\Node;
use Pixers\DoctrineProfilerBundle\Stacktrace\ValueAccessor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * QueryCollector.
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 */
class QueryCollector extends DataCollector
{
    /**
     * @var StackTraceLogger
     */
    protected $logger;

    /**
     * @var Stopwatch
     */
    protected $stopwatch;

    /**
     * @param StackTraceLogger $logger
     * @param Stopwatch        $stopwatch
     */
    public function __construct(StackTraceLogger $logger, Stopwatch $stopwatch)
    {
        $this->logger = $logger;
        $this->stopwatch = $stopwatch;
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param Exception $exception
     */
    public function collect(Request $request, Response $response, Exception $exception = null)
    {
        $this->data['queries'] = $this->logger->getQueries();
    }

    /**
     * @return array
     */
    public function getQueries()
    {
        $queries = array();
        foreach ($this->data['queries'] as $query) {
            $key = $query['trace_hash'].$query['sql_hash'];
            $this->selectQuerySource($query);
            if (isset($queries[$key])) {
                $queries[$key]['count'] += 1;
                $queries[$key]['memory'] += $query['memory'];
                $queries[$key]['hydration_time'] += $query['hydration_time'];
                $queries[$key]['execution_time'] += $query['execution_time'];
            } else {
                $queries[$key] = $query;
                $queries[$key]['count'] = 1;
            }
        }

        return $queries;
    }

    /**
     * Marks selected trace item as query source based on invoking class namespace
     *
     * @param array $query
     */
    protected function selectQuerySource(&$query)
    {
        foreach ($query['trace'] as $i => &$trace) {
            $isSource = true;
            foreach ($this->getNamespacesCutoff() as $namespace) {
                $namespace = trim($namespace, '/\\').'\\';
                if (isset($trace['class']) && strpos($trace['class'], $namespace) !== false) {
                    $isSource = false;
                }
            }
            if ($isSource) {
                $query['trace'][$i - 1]['query_source'] = true;
                break;
            }
        }
    }

    /**
     * Returns query count.
     *
     * @return int
     */
    public function getCount()
    {
        return count($this->data['queries']);
    }

    /**
     * Returns duplicated queries count.
     *
     * @return int
     */
    public function getDuplicatedCount()
    {
        $count = 0;
        foreach ($this->getQueries() as $query) {
            if ($query['count'] > 1) {
                $count += $query['count'] - 1;
            }
        }

        return $count;
    }

    /**
     * Returns queries execution time.
     *
     * @return int
     */
    public function getExecutionTime()
    {
        $time = 1;
        foreach ($this->data['queries'] as $query) {
            $time += $query['execution_time'];
        }
        return $time;
    }

    /**
     * Returns queries memory usage.
     *
     * @return int
     */
    public function getMemoryUsage()
    {
        $memory = 0;
        foreach ($this->data['queries'] as $query) {
            $memory += $query['memory'];
        }

        return $memory;
    }

    /**
     * Return queries hydration time.
     *
     * @return int
     */
    public function getHydrationTime()
    {
        $time = 0;
        foreach ($this->data['queries'] as $query) {
            $time += $query['hydration_time'];
        }

        return $time;
    }

    /**
     * Returns queries stacktrace tree root node
     *
     * @return Node
     */
    public function getCallGraph()
    {
        $node = $root = new Node(array());
        foreach ($this->getQueries() as $query) {
            foreach (array_reverse($query['trace']) as $trace) {
                $node = $node->push($trace);
            }
            $node->addValue($query);
            $node = $root;
        }

        return $root;
    }

    /**
     * Returns filtered/flatten queries stacktrace tree iterator
     *
     * @param int $mode
     *
     * @return FlattenTraceGraphIterator
     */
    public function getFlattenCallGraph($mode = \RecursiveIteratorIterator::SELF_FIRST)
    {
        return new FlattenTraceGraphIterator($this->getCallGraph()->getNodes(), $mode);
    }

    /**
     * @return ValueAccessor
     */
    public function getNodeValueAccessor()
    {
        return new ValueAccessor();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'doctrine_profiler';
    }

    /**
     * Returns "internal" namespaces for query source selection
     *
     * @return array
     */
    protected function getNamespacesCutoff()
    {
        return [
            'Pixers\\DoctrineProfilerBundle',
            'Doctrine',
            'Symfony',
        ];
    }
}
