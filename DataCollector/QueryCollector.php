<?php

namespace Pixers\DoctrineProfilerBundle\DataCollector;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Exception;
use Pixers\DoctrineProfilerBundle\Logging\StackTraceLogger;
use Pixers\DoctrineProfilerBundle\Stacktrace\FlattenTraceGraphIterator;
use Pixers\DoctrineProfilerBundle\Stacktrace\Node;
use Pixers\DoctrineProfilerBundle\Stacktrace\ValueAccessor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Stopwatch\Stopwatch;
use TypeError;

/**
 * QueryCollector.
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 */
class QueryCollector extends DataCollector
{
    /**
     * @var StackTraceLogger[]
     */
    protected $loggers;

    /**
     * @var Stopwatch
     */
    protected $stopwatch;

    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @param Stopwatch        $stopwatch
     * @param ManagerRegistry  $registry
     */
    public function __construct(Stopwatch $stopwatch, ManagerRegistry $registry)
    {
        $this->stopwatch = $stopwatch;
        $this->registry = $registry;
    }

    public function addLogger(string $name, StackTraceLogger $logger)
    {
        $this->loggers[$name] = $logger;
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param Exception $exception
     * @throws DBALException
     */
    public function collect(Request $request, Response $response, Exception $exception = null)
    {
        $queries = [];
        $this->data = [
            'connections_count' => 0,
            'query_count' => 0,
        ];

        foreach ($this->loggers as $name => $logger) {
            $loggerQueries = $logger->getQueries();
            $queries[$name] = $this->sanitizeQueries($name, $loggerQueries);

            $count = count($loggerQueries);
            $this->data['query_count'] += $count;

            if ($count > 0) {
                $this->data['connections_count']++;
            }
        }

        $this->data['queries'] = $queries;
    }

    /**
     * @return array
     */
    public function getQueries()
    {
        $queries = array();
        foreach ($this->data['queries'] as $connection => $connectionQueries) {
            foreach ($connectionQueries as $index => $query) {
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
                    $queries[$key]['connection'] = $connection;
                    $queries[$key]['connection_index'] = $index;
                }
            }
        }

        // Sort queries by execution order, regardless connection's name
        uasort($queries, static function ($a, $b) {
            return $a['connection_index'] <=> $b['connection_index'];
        });

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
    public function getConnectionsCount()
    {
        return $this->data['connections_count'];
    }

    /**
     * Returns query count.
     *
     * @return int
     */
    public function getCount()
    {
        return $this->data['query_count'];
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
        $time = 0;
        foreach ($this->getQueries() as $query) {
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
        foreach ($this->getQueries() as $query) {
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
        foreach ($this->getQueries() as $query) {
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
     * @return void
     */
    public function reset()
    {
        $this->data = array();
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

    /**
     * @param string $connectionName
     * @param array  $queries
     * @return array
     * @throws DBALException
     */
    private function sanitizeQueries(string $connectionName, array $queries): array
    {
        foreach ($queries as $i => $query) {
            $queries[$i] = $this->sanitizeQuery($connectionName, $query);
        }

        return $queries;
    }

    /**
     * @param string $connectionName
     * @param array $query
     * @return array
     * @throws DBALException
     */
    private function sanitizeQuery(string $connectionName, array $query): array
    {
        if (null === $query['params']) {
            $query['params'] = [];
        }

        if (!is_array($query['params'])) {
            $query['params'] = [$query['params']];
        }

        $platform = $this->registry->getConnection($connectionName)->getDatabasePlatform();

        foreach ($query['params'] as $j => $param) {
            if (isset($query['types'][$j])) {
                // Transform the param according to the type
                $type = $query['types'][$j];

                if (is_string($type)) {
                    $type = Type::getType($type);
                }

                if ($type instanceof Type) {
                    $query['types'][$j] = $type->getBindingType();
                    try {
                        $param = $type->convertToDatabaseValue($param, $platform);
                    } catch (TypeError|ConversionException $e) {
                        // Ignore error, might be improved ?
                    }
                }
            }

            [$query['params'][$j], ] = $this->sanitizeParam($param);
        }

        return $query;
    }

    /**
     * Sanitizes a param.
     *
     * The return value is an array with the sanitized value and a boolean
     * indicating if the original value was kept (allowing to use the sanitized
     * value to explain the query).
     *
     * @param $var
     *
     * @return array
     */
    private function sanitizeParam($var): array
    {
        if (is_object($var)) {
            $className = get_class($var);
            return method_exists($var, '__toString') ?
                [sprintf('/* Object(%s): */"%s"', $className, $var->__toString()), false] :
                [sprintf('/* Object(%s) */', $className), false];
        }

        if (is_array($var)) {
            $a = [];
            $original = true;

            foreach ($var as $k => $v) {
                [$value, $orig] = $this->sanitizeParam($v);
                $original = $original && $orig;
                $a[$k] = $value;
            }

            return [$a, $original];
        }

        if (is_resource($var)) {
            return [sprintf('/* Resource(%s) */', get_resource_type($var)), false];
        }

        return [$var, true];
    }
}
