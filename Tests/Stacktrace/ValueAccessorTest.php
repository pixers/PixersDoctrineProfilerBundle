<?php

use Pixers\DoctrineProfilerBundle\Stacktrace\Node;
use Pixers\DoctrineProfilerBundle\Stacktrace\ValueAccessor;

/**
 * ValueAccessorTest
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 *
 */
class ValueAccessorTest extends PHPUnit_Framework_TestCase
{
    public function testValueAcccesor()
    {
        $node = new Node(array());
        $node->addValue(array('time'=>1));
        $node->addValue(array('time'=>2));
        $valueAccessor = new ValueAccessor();
        $this->assertEquals(3, $valueAccessor->sum($node, 'time'));
        $this->assertEquals(0, $valueAccessor->sum($node, 'foo'));
        $node->addValue(array('time'=>3));
        $this->assertEquals(3, $valueAccessor->sum($node, 'time'));
        $valueAccessor2 = new ValueAccessor();
        $this->assertEquals(6, $valueAccessor2->sum($node, 'time'));
    }
}
