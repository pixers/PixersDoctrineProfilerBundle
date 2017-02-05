<?php

namespace Pixers\DoctrineProfilerBundle\Stacktrace;

/**
 * Tree.
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 */
class Node implements \RecursiveIterator
{
    /**
     * @var array
     */
    protected $values = array();

    /**
     * @var array
     */
    protected $trace;

    /**
     * @var Node
     */
    protected $parent;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var Node[]
     */
    public $nodes = array();

    /**
     * @param array $trace
     * @param Node  $parent
     */
    public function __construct(array $trace, Node $parent = null)
    {
        $this->trace = $trace;
        $this->parent = $parent;
        $this->id = $this->createId();
        if ($parent) {
            $parent->nodes[$this->getId()] = $this;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return current($this->nodes);
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return key($this->nodes);
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        next($this->nodes);
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        reset($this->nodes);
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return $this->current() !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function getNodes()
    {
        return $this->nodes;
    }

    /**
     * @return int
     */
    protected function createId()
    {
        static $id = 0;
        
        return ++$id;
    }

    /**
     * @param array $trace
     *
     * @return Node
     */
    public function push($trace)
    {
        foreach ($this as $node) {
            if ($node->getTrace() == $trace) {
                return $node;
            }
        }
        
        $node = new static($trace, $this);
        return $node;
    }

    /**
     * @return Node
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return Node
     */
    public function getRoot()
    {
        return $this->getParent() ? $this->getParent()->getRoot() : $this;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function hasChildren()
    {
        return $this->current() && !empty($this->current()->getNodes());
    }

    /**
     * @return \RecursiveIterator
     */
    public function getChildren()
    {
        return $this->current();
    }

    /**
     * @param mixed $value
     */
    public function addValue($value)
    {
        $this->values[] = $value;
    }

    /**
     * @return mixed
     */
    public function getValues($recursive = false)
    {
        $values = $this->values;
        if ($recursive) {
            foreach ($this as $node) {
                $values = array_merge($values, $node->getValues(true));
            }
        }
        return $values;
    }

    /**
     * @return bool
     */
    public function containsBranch()
    {
        if (count($this->nodes)>1) {
            return true;
        }

        foreach ($this as $node) {
            if ($node->containsBranch()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array
     */
    public function getTrace()
    {
        return $this->trace;
    }
}
