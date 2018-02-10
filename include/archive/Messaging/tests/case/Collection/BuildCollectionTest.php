<?php
use CDash\Messaging\Collection\BuildCollection;

class BuildCollectionTest extends PHPUnit_Framework_TestCase
{
    public function testAdd()
    {
        $build1 = $this->getMock('Build', ['GetName'], [], '', false);
        $build2 = $this->getMock('Build', ['GetName'], [], '', false);
        $build3 = $this->getMock('Build', ['GetName'], [], '', false);

        $build1
            ->expects($this->once())
            ->method('GetName')
            ->will($this->returnValue('ThisIsBuildONe'));

        $build2
            ->expects($this->once())
            ->method('GetName')
            ->will($this->returnValue('ThisIsBuildTwo'));

        $build3
            ->expects($this->once())
            ->method('GetName')
            ->will($this->returnValue('ThisIsBuildThree'));

        $sut = new BuildCollection();

        $this->assertFalse($sut->valid());
        $this->assertNull($sut->key());
        $this->assertNull($sut->current());

        $sut
            ->add($build1)
            ->add($build2)
            ->add($build3);

        $this->assertTrue($sut->valid());
        $this->assertEquals('ThisIsBuildONe', $sut->key());
        $this->assertSame($build1, $sut->current());

        $sut->next();

        $this->assertTrue($sut->valid());
        $this->assertEquals('ThisIsBuildTwo', $sut->key());
        $this->assertSame($build2, $sut->current());

        $sut->next();

        $this->assertTrue($sut->valid());
        $this->assertEquals('ThisIsBuildThree', $sut->key());
        $this->assertSame($build3, $sut->current());

        $sut->next();

        $this->assertFalse($sut->valid());
        $this->assertNull($sut->key());
        $this->assertNull($sut->current());

        $sut->rewind();

        $this->assertTrue($sut->valid());
        $this->assertEquals('ThisIsBuildONe', $sut->key());
        $this->assertSame($build1, $sut->current());
    }

    /**
     * @expectedException \TypeError
     */
    public function testAddBuildThrowsTypeErrorIfArgumentNotBuildClass()
    {
        $sut = new BuildCollection();
        $obj = new stdClass();
        $sut->add($obj);
    }
}
