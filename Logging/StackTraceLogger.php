<?php

namespace Pixers\DoctrineProfilerBundle\Logging;

use Doctrine\DBAL\Cache\ArrayStatement;
use Doctrine\DBAL\Cache\ResultCacheStatement;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
use PDOStatement;

/**
 * StackTraceLogger.
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 */
class StackTraceLogger implements SQLLogger
{

    /**
     * Declared as static in order to sort queries by connections
     *
     * @var int
     */
    protected static $currentQuery = 0;

    /**
     * @var int
     */
    protected static $currentHydration = 0;

    /**
     * @var array
     */
    protected $queries = array();

    /**
     * @var float|null
     */
    protected $start = null;

    /**
     * @var float|null
     */
    protected $hydrationStart = null;

    /**
     * @var int
     */
    protected $memory;

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        $this->memory = memory_get_usage();
        $this->start = microtime(true);
        $this->queries[++self::$currentQuery] = array(
            'sql' => $sql,
            'params' => $params ?? array(),
            'types' => $types,
            'execution_time' => 0,
            'hydrator' => '',
            'hydration_time' => 0,
            'result_count' => 0,
            'cacheable' => false,
            'cached' => false,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {
        $memoryUsage = memory_get_usage();
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $sql = $this->queries[self::$currentQuery]['sql'];
        $this->queries[self::$currentQuery] = array_merge($this->queries[self::$currentQuery], array(
            'execution_time' => microtime(true) - $this->start,
            'trace' => $trace,
            'trace_hash' => md5(json_encode($trace)),
            'sql_hash' => md5($sql),
            'memory' => $memoryUsage - $this->memory
        ));
    }

    /**
     * @param array $result
     */
    public function hydration($result)
    {
        if (!is_array($result)) {
            return;
        }

        $this->queries[self::$currentQuery]['result_count'] += count($result);
    }


    /**
     * @param AbstractHydrator $hydrator
     * @param object $stmt
     * @param array $resultSetMapping
     */
    public function startHydration(AbstractHydrator $hydrator, $stmt, $resultSetMapping)
    {
        ++self::$currentHydration;

        if (self::$currentHydration > self::$currentQuery) {
            $this->createCachedQueryLog($stmt, $resultSetMapping);
        }

        $this->hydrationStart = microtime(true);
        $this->queries[self::$currentQuery]['hydrator'] = get_class($hydrator);
    }

    /**
     * @param object $stmt
     */
    public function stopHydration($stmt)
    {
        if ($stmt instanceof ResultCacheStatement) {
            $this->queries[self::$currentQuery]['cacheable'] = true;
        }

        if ($stmt instanceof ArrayStatement) {
            $this->queries[self::$currentQuery]['cacheable'] = true;
            $this->queries[self::$currentQuery]['cached'] = true;
        }

        if ($stmt instanceof PDOStatement) {
            $this->queries[self::$currentQuery]['row_count'] = $stmt->rowCount();
        } else {
            $this->queries[self::$currentQuery]['row_count'] = null;
        }

        $this->queries[self::$currentQuery]['hydration_time'] = microtime(true) - $this->hydrationStart;
    }

    /**
     * @param object $stmt
     */
    protected function createCachedQueryLog($stmt)
    {
        ++self::$currentQuery;
        $memoryUsage = memory_get_usage();

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $this->queries[self::$currentQuery] = array(
            'params' => [],
            'result_count' => 0,
            'hydration_time' => 0,
            'cacheable' => false,
            'cached' => false,
            'execution_time' => 0,
            'sql' => 'Cached query',
            'trace' => $trace,
            'trace_hash' => md5(json_encode($trace)),
            'sql_hash' => md5(json_encode($trace)),
            'memory' => $memoryUsage - $this->memory,
        );
    }

    /**
     * @return array
     */
    public function getQueries()
    {
        return $this->queries;
    }
}
