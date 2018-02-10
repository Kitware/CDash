<?php
use CDash\Messaging\Collection\BuildCollection;
use CDash\Messaging\Collection\DecoratorCollection;
use CDash\Messaging\Collection\RecipientCollection;
use CDash\Messaging\Collection\TestFailureCollection;
use CDash\Messaging\Email\Decorator\TestFailuresEmailDecorator;
use CDash\Messaging\Email\EmailMessage;
use CDash\Messaging\Message;
use CDash\Messaging\MessageBuilderFactory;
use CDash\Test\CDashTestCase;

/**
 * Integration test of TestHandler
 */
class TestHandlerEmailMessageTest extends CDashTestCase
{
    /** @var  TestingHandler|PHPUnit_Framework_MockObject_MockObject */
    private $handler;
    /** @var  Project|PHPUnit_Framework_MockObject_MockObject */
    private $project;
    /** @var  BuildGroup|PHPUnit_Framework_MockObject_MockObject */
    private $buildGroup;

    public function setUp()
    {
        parent::setUp();
        $this->handler = $this
            ->getMockBuilder('ActionableBuildInterface')
            ->setMethods(['getActionableBuilds', 'getType', 'getBuildGroupId', 'getProjectId'])
            ->getMockForAbstractClass();

        $this->handler
            ->expects($this->any())
            ->method('getType')
            ->willReturn(ActionableBuildInterface::TYPE_TEST);

        $this->handler
            ->expects($this->any())
            ->method('getProjectId')
            ->willReturn('101');

        $this->handler
            ->expects($this->any())
            ->method('getBuildGroupId')
            ->willReturn('201');

        $this->project = $this->getMockProject();
        $this->buildGroup = $this->getMockBuildGroup();
    }

    public function testSmokeTest()
    {
        $this->handler
            ->expects($this->any())
            ->method('getActionableBuilds')
            ->willReturn([]);

        $factory = new MessageBuilderFactory();
        $message = $factory->createMessage($this->handler, Message::TYPE_EMAIL);

        $this->assertInstanceOf(Message::class, $message);
    }

    public function testHandlerHasOneBuildWithNoActionableMessage()
    {
        $this->project
            ->expects($this->never())
            ->method('GetProjectSubscribers')
            ->willReturn([]);

        $mock_build = $this->getMockBuild();
        $mock_build
            ->expects($this->once())
            ->method('GetFailedTests')
            ->willReturn([]);

        $message = new EmailMessage(new DecoratorCollection());
        $message->setProject($this->project);
        $message->setBuildGroup($this->buildGroup);
        $message->setBuildCollection(new BuildCollection([$mock_build]));
        $message->addDecorator(new TestFailuresEmailDecorator(
            new TestFailureCollection(),
            new RecipientCollection()
        ));

        $message->send();

        $messages = $message->getMessages();
        $this->assertEmpty($messages);
    }

    public function testHandlerHasOneBuildWithOneTestFailure()
    {
        // a subscriber who receives an email if the build has the topic (e.g. test failure)
        $mock_user_project_subscriber_1 = $this->getMockUserProject();
        $mock_user_project_subscriber_1->EmailType = UserProject::EMAIL_USER_BUILD_HAS_TOPIC;
        $mock_user_project_subscriber_1->EmailCategory = 32;

        // a subscriber who should never recieve an email
        $mock_user_project_subscriber_2 = $this->getMockUserProject();
        $mock_user_project_subscriber_2->EmailType = UserProject::EMAIL_NEVER;

        // make a subscriber and an author one in the same
        $mock_user_project_commiter_1 = $mock_user_project_subscriber_1;

        // TODO: an author with no account?

        // an author not subscribed to topic
        $mock_user_project_commiter_2 = $this->getMockUserProject();
        $mock_user_project_commiter_2->EmailType = UserProject::EMAIL_ANY_BUILD_HAS_TOPIC;
        $mock_user_project_commiter_2->EmailCategory = 94; //everything except TEST

        $this->project
            ->expects($this->once())
            ->method('GetProjectSubscribers')
            ->willReturn([
                'jon.doe@domain.tld' => $mock_user_project_subscriber_1,
                'claire.temple@domain.tld' => $mock_user_project_subscriber_2
            ]);
        $this->project->EmailBrokenSubmission = 1;

        $mock_test = $this->getMockTest();
        $mock_build = $this->getMockBuild();
        $mock_build
            ->expects($this->once())
            ->method('GetFailedTests')
            ->WillReturn([$mock_test]);

        $mock_build
            ->expects($this->once())
            ->method('GetAuthorsAndCommitters')
            ->willReturn([
                'jon.doe@domain.tld' => $mock_user_project_commiter_1,
                'luke.cage@domain.tld' => $mock_user_project_commiter_2
            ]);

        $mock_build
            ->expects($this->any())
            ->method('buildHasAuthor')
            ->with($mock_user_project_subscriber_1)
            ->willReturn(true);

        $message = new EmailMessage(new DecoratorCollection());
        $message->setProject($this->project);
        $message->setBuildGroup($this->buildGroup);
        $message->setBuildCollection(new BuildCollection([$mock_build]));
        $message->addDecorator(new TestFailuresEmailDecorator(
            new TestFailureCollection(),
            new RecipientCollection()
        ));

        $message->send();
        $messages = $message->getMessages();
        $this->assertArrayHasKey('jon.doe@domain.tld', $messages);
    }
}
