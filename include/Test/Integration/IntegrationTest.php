<?php
use CDash\Collection\SubscriberCollection;
use CDash\Config;
use CDash\Database;
use CDash\Messaging\Notification\Email\EmailBuilder;
use CDash\Messaging\Notification\Email\EmailNotificationFactory;
use CDash\Messaging\Notification\NotificationCollection;
use CDash\Messaging\Notification\NotificationDirector;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Subscription\SubscriptionBuilder;
use CDash\Test\UseCase\TestUseCase;
use CDash\Test\UseCase\UseCase;

class IntegrationTest extends \CDash\Test\CDashUseCaseTestCase
{
    private static $tz;
    private static $database;

    private static $projectId = 2;

    /** @var  Database|PHPUnit_Framework_MockObject_MockObject $db */
    private $db;

    /** @var  ActionableBuildInterface|PHPUnit_Framework_MockObject_MockObject $handler */
    private $handler;

    /** @var  Project|PHPUnit_Framework_MockObject_MockObject $project */
    private $project;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        // set default configuration settings
        $config = Config::getInstance();
        $config->set('CDASH_SERVER_NAME', 'open.cdash.org');
        $config->set('CDASH_BASE_URL', 'http://open.cdash.org');

        // deal with timezone stuff
        self::$tz = date_default_timezone_get();
        date_default_timezone_set('UTC');

        // so that we can mock the database layer
        self::$database = Database::getInstance();
    }

    public static function tearDownAfterClass()
    {
        // restore state
        date_default_timezone_set(self::$tz);
        Database::setInstance(Database::class, self::$database);

        parent::tearDownAfterClass();
    }

    public function setUp()
    {
        parent::setUp();
        $mock_stmt = $this->createMock(PDOStatement::class);
        $mock_stmt
            ->expects($this->any())
            ->method('execute')
            ->willReturn(true);

        $mock_pdo = $this->createMock(PDO::class);
        $mock_pdo
            ->expects($this->any())
            ->method('prepare')
            ->willReturn($mock_stmt);

        $mock_pdo
            ->expects($this->any())
            ->method('query')
            ->willReturn($mock_stmt);

        $this->db = $this->createMock(Database::class);
        $this->db
            ->expects($this->any())
            ->method('getPdo')
            ->willReturn($mock_pdo);

        Database::setInstance(Database::class, $this->db);

        $this->handler = $this->getMockForAbstractClass(ActionableBuildInterface::class);
        $this->project = $this->getMockProject();
        $this->project->EmailMaxItems = 5;
    }

    private function getNotifications()
    {
        $this->project->Id = $this->handler->getProjectId();
        $builder = new SubscriptionBuilder($this->handler, $this->project);

        $subscriptions = $builder->build();
        $director = new NotificationDirector();

        /** @var EmailBuilder $builder */
        $builder = new EmailBuilder(new EmailNotificationFactory(), new NotificationCollection());
        $builder
            ->setSubscriptions($subscriptions)
            ->setProject($this->project);

        return $director->build($builder);
    }

    /**
     * Use case:
     *   - Single user
     *   - Single build
     *   - Build IS of type Test
     *   - User IS subscribed to TestFailure
     *   - User IS NOT subscribed to any labels
     *   - Build contains a single TestFailure
     */
    public function testTopicTestFailure()
    {
        $author = $this->getMockUserProject();

        $project_subscribers = [
            'test_failure_auth@kitware.tld' => $author,
        ];

        $test_id = 101;

        /** @var Build|PHPUnit_Framework_MockObject_MockObject $mock_test_failure */
        $mock_test_failure = $this->getMockBuild();

        $mock_site = $this->getMockSite();

        // we're testing for the notification output of a test failure which begins by setting
        // the input to report test failures
        $mock_test_failure
            ->expects($this->once())
            ->method('GetNumberOfFailedTests')
            ->willReturn(1);

        $mock_test_failure
            ->expects($this->once())
            ->method('GetFailedTests')
            ->willReturn([
                ['name' => 'PHPUnitFail', 'id' => $test_id, 'details' => 'Completed (Failed)'],
            ]);

        $mock_test_failure
            ->expects($this->once())
            ->method('GetName')
            ->willReturn('PHPUnit-Integration-Test');

        $mock_test_failure
            ->expects($this->once())
            ->method('GetSite')
            ->willReturn($mock_site);

        // this sets the author of the build that resulted in a test failure
        // ** We need this only under certain criteria:
        //      * Permissions are set to only email with respect to responsibility of error
        //      * Committers permission is set if committer is not a registered user
        /*
        $mock_test_failure
            ->expects($this->once())
            ->method('GetAuthorsAndCommitters')
            ->willReturn($build_authors);
        */
        $mock_test_failure
            ->expects($this->once())
            ->method('GetSummaryUrl')
            ->willReturn('http://open.cdash.org/buildSummary.php?buildid=101');

        $mock_test_failure->StartTime = '2016-07-28T15:32:14';
        $mock_test_failure->Type = 'Experimental';
        $mock_test_failure->Id = 201;

        $mock_site
            ->expects($this->once())
            ->method('GetName')
            ->willReturn('OphaeleaSite');

        $this->handler
            ->expects($this->once())
            ->method('getActionableBuilds')
            ->willReturn([$mock_test_failure]);

        $this->handler
            ->expects($this->once())
            ->method('getProjectId')
            ->willReturn(self::$projectId);

        // return a list of all authors associated with the project
        $this->project
            ->expects($this->once())
            ->method('GetProjectSubscribers')
            ->willReturnCallback(function (SubscriberCollection $subscribers) use ($project_subscribers) {
                foreach ($project_subscribers as $email => $userProject) {
                    $mask = BitmaskNotificationPreferences::EMAIL_TEST |
                        BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION;
                    $preferences = new BitmaskNotificationPreferences($mask);

                    $subscriber = new Subscriber($preferences);
                    $subscriber->setAddress($email);
                    $subscribers->add($subscriber);
                }
            });

        $this->project
            ->expects($this->once())
            ->method('GetName')
            ->willReturn('BreakerBreaker1-9');

        /** @var \CDash\Collection\Collection $notifications */
        $notifications = $this->getNotifications();

        $this->assertCount(1, $notifications);

        /** @var \CDash\Messaging\Notification\Email\EmailMessage $sut */
        $sut = $notifications->current();
        $this->assertEquals('test_failure_auth@kitware.tld', $sut->getRecipient());

        $body = [
            'You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.',
            '',
            'Details on the submission can be found at http://open.cdash.org/buildSummary.php?buildid=101',
            '',
            'Project: BreakerBreaker1-9',
            "Site: OphaeleaSite",
            'Build Name: PHPUnit-Integration-Test',
            'Build Time: 2016-07-28T15:32:14 UTC',
            'Type: Experimental',
            'Total Tests Failing: 1',
            '',
            '*Tests Failing*',
            "PHPUnitFail | Completed (Failed) | (http://open.cdash.org/testDetails.php?test={$test_id}&buildid={$mock_test_failure->Id})",
            '',
            '-CDash on open.cdash.org',
            ''
        ];

        $expected = join("\n", $body);
        $actual = $sut->getBody();

        $this->assertEquals($expected, $actual);

        $expected = 'FAILED (t=1): BreakerBreaker1-9 - PHPUnit-Integration-Test';
        $actual = $sut->getSubject();

        $this->assertEquals($expected, $actual);
    }

    /**
     * Use case:
     *   - Single user
     *   - Single build
     *   - Build IS of type Test
     *   - User IS subscribed to TestFailure
     *   - User IS NOT subscribed to any labels
     *   - Build contains a single TestFailure
     */
    public function testMultipleTestFailuresWithMultipleSubscribers()
    {
        $useCase = UseCase::createBuilder($this, UseCase::TEST)
            ->createSite([
                'BuildName' => 'SomeOS-SomeBuild',
                'BuildStamp' => '20180122-0100-Experimental',
                'Name' => 'mirror.site',
            ])
            ->setStartTime(1518276772)
            ->setEndTime(1518276773)
            ->createTestFailed('test_fail_one');

        $handler = $useCase->build();
        $this->assertInstanceOf(TestingHandler::class, $handler);
    }
}
