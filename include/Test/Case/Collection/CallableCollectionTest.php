<?php
namespace CDash\Collection;

use CDash\Test\CDashTestCase;

class CallableCollectionTest extends CDashTestCase
{
    public function methodForTestingCallable()
    {
        return 'azybci';
    }

    public function testAdd()
    {
        $sut = new CallableCollection();
        $this->assertCount(0, $sut);

        $callable = [$this, 'methodForTestingCallable'];
        $this->assertTrue(is_callable($callable));
        $sut->add($callable);
        $this->assertCount(1, $sut);

        $key = __CLASS__ . '::methodForTestingCallable';
        $callable = $sut->get($key);
        $this->assertTrue(is_callable($callable));
        $expected = 'azybci';
        $actual = $callable();
        $this->assertEquals($expected, $actual);
    }
}
