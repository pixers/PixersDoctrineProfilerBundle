<?php

use Pixers\DoctrineProfilerBundle\Stacktrace\Node;

/**
 * NodeTest
 *
 * @author Bartłomiej Ojrzeński <bartlomiej.ojrzenski@pixers.pl>
 *
 */
class NodeTest extends PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $node = new Node(array('foo'=>'bar'));
        $this->assertEquals(array('foo'=>'bar'), $node->getTrace());
        
        $parent = new Node(array());
        $child = new Node(array(), $parent);
        $this->assertEquals($parent, $child->getParent());
    }
    
    public function testParenthood()
    {
        $node = new Node(array());
        $this->assertNull($node->getParent());
        $this->assertEquals($node, $node->getRoot());
        
        $parent = new Node(array());
        $child1 = new Node(array(), $parent);
        $child2 = new Node(array(), $child1);
        
        $this->assertEquals($parent, $child1->getParent());
        $this->assertEquals($child1, $child2->getParent());
        $this->assertEquals($parent, $child2->getRoot());
        $this->assertEquals($parent, $child1->getRoot());
    }
    
    public function testChildhood()
    {
        $parent = new Node(array());
        $this->assertEmpty($parent->getNodes());
        $this->assertFalse($parent->containsBranch());
        
        $child1 = new Node(array(), $parent);
        $child2 = new Node(array(), $parent);
        $child3 = new Node(array(), $child2);
        
        $this->assertEquals(2, count($parent->getNodes()));
        $this->assertEquals(1, count($child2->getNodes()));
        $this->assertTrue($parent->containsBranch());
    }
    
    public function testDistinctId()
    {
        $parent = new Node(array());
        $child1 = new Node(array(), $parent);
        $child2 = new Node(array(), $parent);
        $ids = array($parent->getId(), $child1->getId(), $child2->getId());
        $this->assertEquals(count($ids), count(array_unique($ids)));
    }
    
    public function testPush()
    {
        $node = new Node(array());
        $child1 = $node->push(array('dummy'=>'trace'));
        $child2 = $node->push(array('dummy'=>'trace'));
        $child3 = $node->push(array('other'=>'trace'));
        
        $this->assertSame($child1, $child2);
        $this->assertNotSame($child2, $child3);
        
        $this->assertSame($node, $child1->getParent());
        $this->assertSame($node, $child3->getParent());
        
        $this->assertEquals(2, count($node->getNodes()));
        
        $this->assertEquals(array('dummy'=>'trace'), $child1->getTrace());
    }
    
    public function testValues()
    {
        $parent = new Node(array());
        $child1 = new Node(array(), $parent);
        $child2 = new Node(array(), $child1);
        
        $parent->addValue(1);
        $parent->addValue(2);
        $child1->addValue(3);
        $child1->addValue(4);
        $child2->addValue(5);

        $this->assertEquals(array(1,2), $parent->getValues());
        $this->assertEquals(array(1,2,3,4,5), $parent->getValues(true));
    }
    
    public function testInterfaces()
    {
        $parent = new Node(array());
        $child1 = new Node(array(), $parent);
        $child2 = new Node(array(), $parent);
        $child3 = new Node(array(), $child1);
        
        $iterated = array();
        $iterated[$child1->getId()]=$child1;
        $iterated[$child2->getId()]=$child2;
        
        $this->assertSame($iterated, iterator_to_array($parent));
        
        $this->assertTrue($parent instanceof RecursiveIterator);
        $parent->rewind();
        $this->assertTrue($parent->hasChildren());
        $this->assertTrue($parent->getChildren() instanceof RecursiveIterator);
    }
}
