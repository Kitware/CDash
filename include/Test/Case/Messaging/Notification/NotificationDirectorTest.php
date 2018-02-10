<?php
use CDash\Messaging\Notification\NotificationBuilderInterface;
use CDash\Messaging\Notification\NotificationDirector;

class NotificationDirectorTest extends \CDash\Test\CDashTestCase
{
    public function testBuild()
    {
        $mock_builder = $this->getMockForAbstractClass(NotificationBuilderInterface::class);

        $mock_builder
            ->expects($this->once())
            ->method('createNotifications');

        $mock_builder
            ->expects($this->once())
            ->method('setSender');

        $mock_builder
            ->expects($this->once())
            ->method('setRecipient');

        $mock_builder
            ->expects($this->once())
            ->method('setSubject');

        $mock_builder
            ->expects($this->once())
            ->method('setBody');

        $mock_builder
            ->expects($this->once())
            ->method('getNotifications');

        $sut = new NotificationDirector();
        $sut->build($mock_builder);
    }
}
