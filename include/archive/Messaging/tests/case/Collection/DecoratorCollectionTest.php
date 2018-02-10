<?php
use CDash\Messaging\Collection\DecoratorCollection;
use CDash\Messaging\Email\Decorator\TestFailuresEmailDecorator;
use CDash\Messaging\Email\Decorator\TestWarningsEmailDecorator;

class DecoratorCollectionTest extends PHPUnit_Framework_TestCase
{
    public function testAdd()
    {
        $sut = new DecoratorCollection();

        $this->assertFalse($sut->valid());

        $d1 = new TestFailuresEmailDecorator();
        $d2 = new TestWarningsEmailDecorator();
        $sut
            ->add($d1)
            ->add($d2);

        $this->assertTrue($sut->valid());
    }

    /**
     * @expectedException \TypeError
     */
    public function testAddThrowsTypeErrorIfArgumentNotDecoratorInterface()
    {
        $sut = new DecoratorCollection();
        $obj = new stdClass();
        $sut->add($obj);
    }
}
