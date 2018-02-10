<?php
use CDash\Messaging\Collection\Collection;

class CollectionTest extends PHPUnit_Framework_TestCase
{
    public function testConstructorWithNumIndexedArray()
    {
        $array = ['one', 'two', 'three', 'four'];
        $sut = new Collection($array);

        $this->assertTrue($sut->valid());
        $this->assertEquals('0', $sut->key());
        $this->assertEquals('one', $sut->current());

        $sut->next();
        $this->assertTrue($sut->valid());
        $this->assertEquals('1', $sut->key());
        $this->assertEquals('two', $sut->current());

        $sut->next();
        $this->assertTrue($sut->valid());
        $this->assertEquals('2', $sut->key());
        $this->assertEquals('three', $sut->current());

        $sut->next();
        $this->assertTrue($sut->valid());
        $this->assertEquals('3', $sut->key());
        $this->assertEquals('four', $sut->current());

        $sut->next();
        $this->assertFalse($sut->valid());
        $this->assertNull($sut->key());

        $sut->next();
        $this->assertFalse($sut->valid());
        $this->assertNull($sut->key());

        $sut->rewind();$this->assertTrue($sut->valid());
        $this->assertEquals('0', $sut->key());
        $this->assertEquals('one', $sut->current());
    }

    public function testConstructorWithAssociativeArray()
    {
        $array = ['one' => 'isa', 'two' => 'dalawa', 'three' => 'tatlo', 'four' => 'lipat'];
        $sut = new Collection($array);

        $this->assertTrue($sut->valid());
        $this->assertEquals('one', $sut->key());
        $this->assertEquals('isa', $sut->current());

        $sut->next();
        $this->assertTrue($sut->valid());
        $this->assertEquals('two', $sut->key());
        $this->assertEquals('dalawa', $sut->current());

        $sut->next();
        $this->assertTrue($sut->valid());
        $this->assertEquals('three', $sut->key());
        $this->assertEquals('tatlo', $sut->current());

        $sut->next();
        $this->assertTrue($sut->valid());
        $this->assertEquals('four', $sut->key());
        $this->assertEquals('lipat', $sut->current());

        $sut->next();
        $this->assertFalse($sut->valid());
        $this->assertNull($sut->key());

        $sut->next();
        $this->assertFalse($sut->valid());
        $this->assertNull($sut->key());

        $sut->rewind();$this->assertTrue($sut->valid());
        $this->assertEquals('one', $sut->key());
        $this->assertEquals('isa', $sut->current());
    }

    public function testAdd()
    {
        $sut = new Collection();

        $this->assertFalse($sut->valid());

        $std1 = new stdClass();
        $sut->add($std1);

        $this->assertTrue($sut->valid());
        $this->assertSame($std1, $sut->current());

        $sut->next();
        $this->assertFalse($sut->valid());
    }

    public function testHasItems()
    {
        $sut = new Collection();
        $this->assertFalse($sut->hasItems());

        $sut->add(new stdClass());
        $this->assertTrue($sut->hasItems());
    }
}
