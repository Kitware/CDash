<?php
use Topic\TestFailureTopic;

class TopicTests extends \CDash\Test\CDashTestCase
{
    /** @var  ActionableBuildInterface|PHPUnit_Framework_MockObject_MockObject $handler */
    private $handler;
    public function setUp()
    {
        $this->handler = $this->getMockBuilder(['ActionableBuildInterface'])
            ->enableArgumentCloning()
            ->getMock();
    }
    public function testTestFailureTopic()
    {
        $m1_build = $this->getMockBuild();
        $m1_build
            ->expects($this->atLeastOnce())
            ->method('GetNumberOfFailedTests')
            ->willReturn(null);

        $m2_build = $this->getMockBuild();
        $m2_build
            ->expects($this->atLeastOnce())
            ->method('GetNumberOfFailedTests')
            ->willReturn(-1);

        $m3_build = $this->getMockBuild();
        $m3_build
            ->expects($this->atLeastOnce())
            ->method('GetNumberOfFailedTests')
            ->willReturn(0);

        $m4_build = $this->getMockBuild();
        $m4_build
            ->expects($this->atLeastOnce())
            ->method('GetNumberOfFailedTests')
            ->willReturn(1);

        $this->handler
            ->expects($this->at(0))
            ->method('getActionableBuilds')
            ->willReturn([]);

        $this->handler
            ->expects($this->at(1))
            ->method('getActionableBuilds')
            ->willReturn([$m1_build]);

        $this->handler
            ->expects($this->at(2))
            ->method('getActionableBuilds')
            ->willReturn([$m1_build, $m2_build]);

        $this->handler
            ->expects($this->at(3))
            ->method('getActionableBuilds')
            ->willReturn([$m1_build, $m2_build, $m3_build]);

        $this->handler
            ->expects($this->at(4))
            ->method('getActionableBuilds')
            ->willReturn([$m1_build, $m2_build, $m3_build, $m4_build]);

        $sut = new TestFailureTopic();

        // has no actionable builds
        $this->assertFalse($sut->hasTopic($this->handler));

        // has one actionable build, with TestFailedCount not set
        $this->assertFalse($sut->hasTopic($this->handler));

        // has two builds with TestCountFailed set to null, -1
        $this->assertFalse($sut->hasTopic($this->handler));

        // has three builds with TestCountFailed set to null, -1, 0
        $this->assertFalse($sut->hasTopic($this->handler));

        // has four builds with last TestCountFailed set to 1
        $this->assertTrue($sut->hasTopic($this->handler));
    }

    public function testNotRunTopic()
    {
        $this->markTestSkipped('TODO: testNotRunTopic');
    }
}
