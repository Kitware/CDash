<?php
use CDash\Messaging\Topic\TestFailureTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Build;

class TestFailureTopicTest extends \CDash\Test\CDashTestCase
{
    public function testSubscribesToBuild()
    {
        $sut = new TestFailureTopic();
        $build = new Build();
        $this->assertFalse($sut->subscribesToBuild($build));

        $build->TestFailedCount = 0;
        $this->assertFalse($sut->subscribesToBuild($build));

        $build->TestFailedCount = 1;
        $this->assertTrue($sut->subscribesToBuild($build));

        $build->TestFailedCount = '1';
        $this->assertTrue($sut->subscribesToBuild($build));

        // Topics are decorators, so the creation of this mock topic is merely
        // for testing all possible states of our SUT
        $mock_topic = $this->getMockForAbstractClass(Topic::class);
        $mock_topic
            ->method('subscribesToBuild')
            ->willReturnOnConsecutiveCalls(false, false, false, true, true);

        $sut = new TestFailureTopic($mock_topic);
        $build = new Build();
        // parent does not subscribe to build and TestFailedCount is default value
        $this->assertFalse($sut->subscribesToBuild($build));

        // parent does not subscribe to build and TestFailedCount is 0
        $build->TestFailedCount = 0;
        $this->assertFalse($sut->subscribesToBuild($build));

        // parent does not subscribe to build and TestFailedCount is 1
        $build->TestFailedCount = 1;
        $this->assertFalse($sut->subscribesToBuild($build));

        // parent *subscribes* to build and TestFailedCount is 0
        $build->TestFailedCount = 0;
        $this->assertFalse($sut->subscribesToBuild($build));

        // parent *subscribes* to build and TestFailedCount is 1
        $build->TestFailedCount = 1;
        $this->assertTrue($sut->subscribesToBuild($build));
    }

    public function testGetTopicName()
    {
        $sut = new TestFailureTopic();
        $expected = 'TestFailure';
        $actual = $sut->getTopicName();
        $this->assertEquals($expected, $actual);
    }
}
