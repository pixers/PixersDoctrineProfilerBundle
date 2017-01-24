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
     * @var array
     */
    protected $queries = array();
    
    /**
     * @var float|null
     */
    protected $start = null;
    
    /**
     * @var int
     */
    protected $currentQuery = 0;
    
    /**
     * @var int
     */
    protected $currentHydration = 0;
    
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
        $this->queries[++$this->currentQuery] = array(
            'sql' => $sql,
            'params' => $params,
            'types' => $types,
            'execution_time' => 0,
            'hydrator' => '',
            'hydration_time' => 0,
            'result_count' => 0,
            'cacheable' => false,
            'cached' => false
        );
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {
        $memoryUsage = memory_get_usage();
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $sql = $this->queries[$this->currentQuery]['sql'];
        $this->queries[$this->currentQuery] = array_merge($this->queries[$this->currentQuery], array(
            'execution_time' => microtime(true) - $this->start,
            'trace' => $trace,
            'trace_hash' => md5(serialize($trace)),
            'sql_hash' => md5($sql),
            'memory' => $memoryUsage - $this->memory
        ));
    }
    
    /**
     * @param array $result
     */
    public function hydration($result)
    {
        $this->queries[$this->currentQuery]['result_count'] += count($result);
    }
    

    /**
     * @param AbstractHydrator $hydrator
     * @param object $stmt
     * @param array $resultSetMapping
     */
    public function startHydration(AbstractHydrator $hydrator, $stmt, $resultSetMapping)
    {
        ++$this->currentHydration;
        
        if ($this->currentHydration > $this->currentQuery) {
            $this->createCachedQueryLog($stmt, $resultSetMapping);
        }
        
        $this->hydrationStart = microtime(true);
        $this->queries[$this->currentQuery]['hydrator'] = get_class($hydrator);
    }
    
    /**
     * @param object $stmt
     */
    public function stopHydration($stmt)
    {
        if ($stmt instanceof ResultCacheStatement) {
            $this->queries[$this->currentQuery]['cacheable'] = true;
        }
        
        if ($stmt instanceof ArrayStatement) {
            $this->queries[$this->currentQuery]['cacheable'] = true;
            $this->queries[$this->currentQuery]['cached'] = true;
        }
               
        if ($stmt instanceof PDOStatement) {
            $this->queries[$this->currentQuery]['row_count'] = $stmt->rowCount();
        } else {
            $this->queries[$this->currentQuery]['row_count'] = null;
        }
        
        $this->queries[$this->currentQuery]['hydration_time'] = microtime(true) - $this->hydrationStart;
    }
    
    /**
     * @param object $stmt
     */
    protected function createCachedQueryLog($stmt)
    {
        ++$this->currentQuery;
        $memoryUsage = memory_get_usage();
        
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        
        $this->queries[$this->currentQuery] = array(
            'params' => null,
            'result_count' => 0,
            'hydration_time' => 0,
            'cacheable' => false,
            'cached' => false,
            'execution_time' => 0,
            'sql' => 'Cached query',
            'trace' => $trace,
            'trace_hash' => md5(serialize($trace)),
            'sql_hash' => md5(serialize($trace)),
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
