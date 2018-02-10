<?php

use CDash\Messaging\Email\Decorator\BuildFailureErrorsEmailDecorator;
use CDash\Messaging\Email\Decorator\BuildFailureWarningsEmailDecorator;
use CDash\Messaging\Email\Decorator\BuildErrorEmailDecorator;
use CDash\Messaging\Email\Decorator\BuildWarningsEmailDecorator;
use CDash\Messaging\Email\Decorator\ConfigureErrorsEmailDecorator;
use CDash\Messaging\Email\Decorator\DynamicAnalysisEmailDecorator;
use CDash\Messaging\Email\Decorator\TestFailuresEmailDecorator;
use CDash\Messaging\Email\Decorator\TestWarningsEmailDecorator;
use CDash\Messaging\Email\Decorator\MissingTestsEmailDecorator;
use CDash\Messaging\Email\Decorator\UpdateErrorsEmailDecorator;
use CDash\Messaging\Email\EmailDigestMessage;
use CDash\Messaging\Email\EmailMessage;
use CDash\Messaging\MessageBuilderFactory;
use CDash\Messaging\Message;

class MessageBuilderFactoryTest extends \PHPUnit_Framework_TestCase
{
    public static $_pdo_single_row_query = [];

    /** @var  ActionableBuildInterface|PHPUnit_Framework_MockObject_MockObject $handler */
    private $handler;

    private $project;
    private $build;
    private $buildGroup;

    /** @var  MessageBuilderFactory $sut */
    private $sut;

    public function setUp()
    {
        parent::setUp();
        $this->project = $this->getMock('\Project', ['Fill'], [], '', false);
        $this->project->EmailBrokenSubmission = 1;

        $this->build = $this->getMock(
            '\Build',
            ['FillFromId', 'GetGroup', 'GetName'],
            [],
            '',
            false
        );

        $this->buildGroup = $this->getMock(
            '\BuildGroup',
            ['GetSummaryEmail', 'GetEmailCommitters', 'SetId'],
            [],
            '',
            false
        );

        $this->handler = $this->getMock(
            '\ActionableBuildInterface',
            ['getActionableBuilds', 'getType', 'getProjectId', 'getBuildGroupId']
        );

        $this->sut = new MessageBuilderFactory();
    }
    /*
    public function testCreateMessageReturnsEmailDigestMessage()
    {
        $buildGroupId = 102;

        $this->handler
            ->expects($this->once())
            ->method('getActionableBuilds')
            ->will($this->returnValue([$this->build]));

        $this->handler
            ->expects($this->once())
            ->method('getBuildGroupId')
            ->will($this->returnValue($buildGroupId));

        $this->handler
            ->expects($this->once())
            ->method('getProjectId')
            ->will($this->returnValue(1));

        $this->buildGroup
            ->expects($this->once())
            ->method('SetId')
            ->with($this->equalTo($buildGroupId));

        $this->buildGroup
            ->expects($this->once())
            ->method('GetSummaryEmail')
            ->will($this->returnValue(BuildGroup::EMAIL_SUMMARY));

        $message = $this->sut->createMessage($this->handler, Message::TYPE_EMAIL);
        $this->assertInstanceOf(EmailDigestMessage::class, $message);
    }
    */

    public function testCreateMessageReturnsEmailMessageForBuild()
    {
        $this->handler
            ->expects($this->once())
            ->method('getActionableBuilds')
            ->will($this->returnValue([$this->build]));

        $this->handler
            ->expects($this->once())
            ->method('getType')
            ->will($this->returnValue(\ActionableBuildInterface::TYPE_BUILD));

        $this->buildGroup
            ->expects($this->once())
            ->method('GetSummaryEmail')
            ->will($this->returnValue(BuildGroup::EMAIL_DEFER));

        $message = $this->sut->createMessage($this->handler, Message::TYPE_EMAIL);
        $this->assertInstanceOf(EmailMessage::class, $message);

        $decorators = $message->getDecoratorCollection();

        $this->assertCount(4, $decorators);
        $this->assertInstanceOf(BuildErrorEmailDecorator::class, $decorators->current());

        $decorators->next();
        $this->assertInstanceOf(BuildWarningsEmailDecorator::class, $decorators->current());

        $decorators->next();
        $this->assertInstanceOf(BuildFailureErrorsEmailDecorator::class, $decorators->current());

        $decorators->next();
        $this->assertInstanceOf(BuildFailureWarningsEmailDecorator::class, $decorators->current());
    }

    public function testCreateMessageReturnsEmailMessageForConfigure()
    {
        $this->handler
            ->expects($this->once())
            ->method('getProjectId')
            ->will($this->returnValue(1));

        $this->handler
            ->expects($this->once())
            ->method('getActionableBuilds')
            ->will($this->returnValue([$this->build]));

        $this->handler
            ->expects($this->once())
            ->method('getType')
            ->will($this->returnValue(\ActionableBuildInterface::TYPE_CONFIGURE));

        $this->buildGroup
            ->expects($this->once())
            ->method('GetSummaryEmail')
            ->will($this->returnValue(BuildGroup::EMAIL_DEFER));

        $message = $this->sut->createMessage($this->handler, Message::TYPE_EMAIL);
        $this->assertInstanceOf(EmailMessage::class, $message);

        $decorators = $message->getDecoratorCollection();

        $this->assertCount(1, $decorators);
        $this->assertInstanceOf(ConfigureErrorsEmailDecorator::class, $decorators->current());
    }

    public function testCreateMessageReturnsEmailMessageForDynamicAnalysis()
    {
        $this->handler
            ->expects($this->once())
            ->method('getProjectId')
            ->will($this->returnValue(1));

        $this->handler
            ->expects($this->once())
            ->method('getActionableBuilds')
            ->will($this->returnValue([$this->build]));

        $this->handler
            ->expects($this->once())
            ->method('getType')
            ->will($this->returnValue(\ActionableBuildInterface::TYPE_DYNAMIC_ANALYSIS));


        $this->buildGroup
            ->expects($this->once())
            ->method('GetSummaryEmail')
            ->will($this->returnValue(BuildGroup::EMAIL_DEFER));

        $message = $this->sut->createMessage($this->handler, Message::TYPE_EMAIL);
        $this->assertInstanceOf(EmailMessage::class, $message);

        $decorators = $message->getDecoratorCollection();

        $this->assertCount(1, $decorators);
        $this->assertInstanceOf(DynamicAnalysisEmailDecorator::class, $decorators->current());
    }

    public function testCreateMessageReturnsEmailMessageForTest()
    {
        $this->handler
            ->expects($this->once())
            ->method('getProjectId')
            ->will($this->returnValue(1));

        $this->handler
            ->expects($this->once())
            ->method('getActionableBuilds')
            ->will($this->returnValue([$this->build]));

        $this->handler
            ->expects($this->once())
            ->method('getType')
            ->will($this->returnValue(\ActionableBuildInterface::TYPE_TEST));

        $this->buildGroup
            ->expects($this->once())
            ->method('GetSummaryEmail')
            ->will($this->returnValue(BuildGroup::EMAIL_DEFER));

        $message = $this->sut->createMessage($this->handler, Message::TYPE_EMAIL);
        $this->assertInstanceOf(EmailMessage::class, $message);

        $decorators = $message->getDecoratorCollection();

        $this->assertCount(3, $decorators);
        $this->assertInstanceOf(TestFailuresEmailDecorator::class, $decorators->current());

        $decorators->next();
        $this->assertInstanceOf(TestWarningsEmailDecorator::class, $decorators->current());

        $decorators->next();
        $this->assertInstanceOf(MissingTestsEmailDecorator::class, $decorators->current());
    }

    public function testCreateMessageReturnsEmailMessageForUpdate()
    {
        $this->handler
            ->expects($this->once())
            ->method('getProjectId')
            ->will($this->returnValue(1));

        $this->handler
            ->expects($this->once())
            ->method('getActionableBuilds')
            ->will($this->returnValue([$this->build]));

        $this->handler
            ->expects($this->once())
            ->method('getType')
            ->will($this->returnValue(\ActionableBuildInterface::TYPE_UPDATE));

        $this->buildGroup
            ->expects($this->once())
            ->method('GetSummaryEmail')
            ->will($this->returnValue(BuildGroup::EMAIL_DEFER));

        $message = $this->sut->createMessage($this->handler, Message::TYPE_EMAIL);
        $this->assertInstanceOf(EmailMessage::class, $message);

        $decorators = $message->getDecoratorCollection();

        $this->assertCount(1, $decorators);
        $this->assertInstanceOf(UpdateErrorsEmailDecorator::class, $decorators->current());
    }
}
