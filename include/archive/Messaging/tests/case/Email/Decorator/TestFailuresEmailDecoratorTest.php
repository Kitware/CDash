<?php
use CDash\Messaging\Email\Decorator\TestFailuresEmailDecorator;
use CDash\Test\CDashTestCase;

class TestFailuresEmailDecoratorTest extends CDashTestCase
{
    public function testHasTopicHasWithNoTopicsReturnsFalse()
    {
        $mock_build = $this->getMockBuild();
        $mock_build->TestFailedCount = 0;
        $sut = new TestFailuresEmailDecorator();
        $this->assertEmpty($sut->getTemplateTopicItems($mock_build, ''));
    }
}
