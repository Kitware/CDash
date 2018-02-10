<?php

use CDash\Messaging\Collection\BuildCollection;
use CDash\Messaging\Collection\BuildErrorCollection;
use CDash\Messaging\Collection\DecoratorCollection;
use CDash\Messaging\Collection\RecipientCollection;
use CDash\Messaging\Email\Decorator\EmailDecorator;
use CDash\Messaging\Email\EmailMessage;

class EmailDecoratorTest extends PHPUnit_Framework_TestCase
{
    private $users;

    public function setUp()
    {
        parent::setUp();

        // User with email set to never
        $user1 = $this->getMock('UserProject', [], [], '', false);
        $user1->EmailType = 0;

        // user with email set to receive basically everything
        $user2 = $this->getMock('UserProject', [], [], '', false);
        $user2->EmailType = 3;
        $user2->EmailCategory = 126;

        // user with email set to receive from everyone if nightly build
        $user3 = $this->getMock('UserProject', [], [], '', false);
        $user3->EmailType = 2;
        $user3->EmailCategory = 126;
        $user3->UserId = 321;

        // user with email set to receive from everyone but only BUILD_ERROR topic
        $user4 = $this->getMock('UserProject', [], [], '', false);
        $user4->EmailType = 3;
        $user4->EmailCategory = 16;

        // user with email set to receive only if his/her build is causing error with topic
        $user5 = $this->getMock('UserProject', [], [], '', false);
        $user5->EmailType = 1;
        $user5->EmailCategory = 126;
        $user5->UserId = 324;

        $this->users = [
            'user1@tld.com' => $user1,
            'user2@tld.com' => $user2,
            'user3@tld.com' => $user3,
            'user4@tld.com' => $user4,
            'user5@tld.com' => $user5
        ];
    }

    public function testHasRecipients()
    {
        $topics = new BuildErrorCollection();
        $recipients = new RecipientCollection();

        /** @var EmailDecorator|PHPUnit_Framework_MockObject_MockObject $sut */
        $sut = $this->getMockForAbstractClass(EmailDecorator::class, [null, $topics, $recipients]);
        $sut
            ->expects($this->once())
            ->method('getTopicName')
            ->willReturn(EmailDecorator::TOPIC_ERROR);

        $project = $this->getMock('Project', [], [], '', false);
        $buildGroup = $this->getMock('BuildGroup', [], [], '', false);
        $builds = new BuildCollection();
        $decorators = new DecoratorCollection();

        $message = new EmailMessage($project, $buildGroup, $builds, $decorators);

        $sut->setMessage($message);

        $project
            ->expects($this->once())
            ->method('GetProjectUsers')
            ->will($this->returnValue($this->users));

        $this->assertTrue($sut->hasRecipients());

        $recipients = $sut->getRecipientCollection();
        $this->assertCount(2, $recipients);

        $expected = 'user2@tld.com';
        $actual = $recipients->key();
        $this->assertEquals($expected, $actual);

        $recipients->next();
        $expected = 'user4@tld.com';
        $actual = $recipients->key();
        $this->assertEquals($expected, $actual);
    }
}
