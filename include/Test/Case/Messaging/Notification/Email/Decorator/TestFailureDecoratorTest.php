<?php

use CDash\Messaging\Notification\Email\Decorator\TestFailureDecorator;
use CDash\Messaging\Notification\Email\EmailMessage;
use CDash\Messaging\Topic\TestFailureTopic;
use CDash\Messaging\Topic\TopicInterface;

class TestFailureDecoratorTest extends \CDash\Test\CDashTestCase
{
    public function testWith()
    {
        /** @var TopicInterface|PHPUnit_Framework_MockObject_MockObject $mock_topic */
        $mock_topic = $this->getMockBuilder(TestFailureTopic::class)
            ->setMethods(['getTopicData'])
            ->getMock();

        $mock_topic
            ->expects($this->once())
            ->method('getTopicData')
            ->willReturn([
                ['name' => 'Name 1', 'details' => 'Details 1', 'url' => 'Url 1'],
                ['name' => 'Name 2', 'details' => 'Details 2', 'url' => 'Url 2'],
            ]);
        $emailMessage = new EmailMessage();

        $sut = new TestFailureDecorator();
        $sut
            ->decorate($emailMessage)
            ->with($mock_topic->getTopicData());

        $expected = "\n*Tests failing*\nName 1 | Details 1 | Url 1\nName 2 | Details 2 | Url 2\n\n";
        $actual = "{$sut}";

        $this->assertEquals($expected, $actual);
    }
}
