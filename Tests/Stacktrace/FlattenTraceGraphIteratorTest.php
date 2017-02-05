<?php

use Pixers\DoctrineProfilerBundle\Stacktrace\FlattenTraceGraphIterator;
use Pixers\DoctrineProfilerBundle\Stacktrace\Node;

/**
 * FlattenTraceGraphIteratorTest
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 *
 */
class FlattenTraceGraphIteratorTest extends PHPUnit_Framework_TestCase
{
    public function testFlattenIterator()
    {
        $node0 = new Node(array());
        $node1 = new Node(array(), $node0); //include child count greater then 1
        $node2 = new Node(array(), $node0); //exclude child count equal to 1
        $node3 = new Node(array(), $node0); //include - contains values
        $node3->addValue(1);
        $node4 = new Node(array('query_source'=>true), $node0); //include - is query source
        $node5 = new Node(array(), $node0); //exclude child count equal to 1
        
        $node1_1 = new Node(array(), $node1);//exclude
        $node1_2 = new Node(array(), $node1);//exclude
        $node2_1 = new Node(array(), $node2);//exclude
        $node5_1 = new Node(array(), $node5);//include - contains values
        $node5_1->addValue(1);
        
        $iterator = new FlattenTraceGraphIterator(array($node0));
        
        $this->assertSame(array($node0), iterator_to_array($iterator));
        $iterator->rewind();
        $this->assertSame(array($node1,$node3,$node4,$node5_1), iterator_to_array($iterator->getChildren()));
    }
}
