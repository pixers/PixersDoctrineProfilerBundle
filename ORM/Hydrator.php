<?php

namespace Pixers\DoctrineProfilerBundle\ORM;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
use Pixers\DoctrineProfilerBundle\Logging\StackTraceLogger;
use ReflectionClass;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Profileable Hydrator wrapper.
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 */
class Hydrator extends AbstractHydrator
{
    /**
     * @var Stopwatch
     */
    protected $stopWatch;

    /**
     * @var AbstractHydrator
     */
    protected $hydrator;

    /**
     * @var string
     */
    protected $stopWatchName;

    /**
     *
     * @var StackTraceLogger
     */
    protected $logger;

    /**
     * @param EntityManagerInterface $em
     * @param AbstractHydrator       $hydrator
     * @param Stopwatch              $stopWatch
     * @param string|null            $stopWatchName
     */
    public function __construct(EntityManagerInterface $em, AbstractHydrator $hydrator, StackTraceLogger $logger, Stopwatch $stopWatch, $stopWatchName = null)
    {
        parent::__construct($em);
        $this->hydrator = $hydrator;
        $this->stopWatch = $stopWatch;
        $this->logger = $logger;
        $this->setStopWatchName($stopWatchName);
    }

    /**
     * @return string
     */
    public function getStopWatchName()
    {
        if ($this->stopWatchName === null) {
            $reflection = new ReflectionClass($this->hydrator);
            $this->stopWatchName = 'doctrine.hydrator.'.$reflection->getShortName();
        }

        return $this->stopWatchName;
    }

    /**
     * @param string $name
     *
     * @return Hydrator
     */
    public function setStopWatchName($name = null)
    {
        $this->stopWatchName = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function iterate($stmt, $resultSetMapping, array $hints = array())
    {
        $this->hydrator->iterate($stmt, $resultSetMapping, $hints);

        return parent::iterate($stmt, $resultSetMapping, $hints);
    }

    /**
     * {@inheritdoc}
     */
    protected function hydrateAllData()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function hydrateAll($stmt, $resultSetMapping, array $hints = array())
    {
        $this->stopWatch->start($this->getStopWatchName());
        $this->logger->startHydration($this->hydrator, $this->_stmt, $this->_rsm);
        $result = $this->hydrator->hydrateAll($stmt, $resultSetMapping, $hints);
        $this->logger->hydration($result);
        $this->logger->stopHydration($stmt);
        $this->stopWatch->stop($this->getStopWatchName());

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function prepare()
    {
        $this->stopWatch->start($this->getStopWatchName());
        $this->logger->startHydration($this->hydrator, $this->_stmt, $this->_rsm);

        return parent::prepare();
    }

    /**
     * {@inheritdoc}
     */
    public function hydrateRow()
    {
        $row = $this->hydrator->hydrateRow();

        if ($row === false) {
            $this->logger->stopHydration($this->_stmt);
            $this->stopWatch->stop($this->getStopWatchName());
        } else {
            $this->logger->hydration(array($row));
        }

        return $row;
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->hydrator, $name), $arguments);
    }
}
