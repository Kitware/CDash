<?php
use CDash\Messaging\Topic\TestFailureTopic;

class TestFailureTopicTest extends \CDash\Test\CDashTestCase
{
    public function testGetTopicName()
    {
        $sut = new TestFailureTopic();
        $expected = 'TestFailure';
        $actual = $sut->getTopicName();
        $this->assertEquals($expected, $actual);
    }
}
