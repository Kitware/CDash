<?php
use CDash\Collection\SubscriberCollection;
use CDash\Config;
use CDash\Database;
use CDash\Messaging\Notification\Email\EmailBuilder;
use CDash\Messaging\Notification\Email\EmailNotificationFactory;
use CDash\Messaging\Notification\NotificationCollection;
use CDash\Messaging\Notification\NotificationDirector;
use CDash\Messaging\Notification\NotificationInterface;
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

    /** @var  ActionableBuildInterface $submission */
    private $submission;

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
        parent::setUp();
    }

    /**
     * @param UseCase $useCase
     * @return NotificationCollection
     */
    private function getNotifications(array $subscribers)
    {
        $this->submission = $this->useCase->build();
        $project = $this->submission->GetProject();
        $project->EmailMaxItems = 5;
        $subscriberCollection = new SubscriberCollection();

        foreach ($subscribers as $entry) {
            $email = $entry[0];
            $settings = $entry[1];
            $labels = isset($entry[2]) ? $entry[2] : [];
            $preferences = new BitmaskNotificationPreferences($settings);
            $subscriber = new Subscriber($preferences);
            $subscriber
                ->setAddress($email)
                ->setLabels($labels);
            $subscriberCollection->add($subscriber);
        }

        $project->SetSubscriberCollection($subscriberCollection);

        $builder = new SubscriptionBuilder($this->submission);

        $subscriptions = $builder->build();
        $director = new NotificationDirector();

        /** @var EmailBuilder $builder */
        $builder = new EmailBuilder(new EmailNotificationFactory(), new NotificationCollection());
        $builder
            ->setSubscriptions($subscriptions)
            ->setProject($this->submission->GetProject());

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
    public function bestTopicTestFailure()
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

        $this->submission
            ->expects($this->once())
            ->method('getActionableBuilds')
            ->willReturn([$mock_test_failure]);

        $this->submission
            ->expects($this->once())
            ->method('getProjectId')
            ->willReturn(self::$projectId);

        // return a list of all authors associated with the project
        $this->project
            ->expects($this->once())
            ->method('GetSubscriberCollection')
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
        $this->useCase = UseCase::createBuilder($this, UseCase::TEST)
            ->createSite([
                'BuildName' => 'SomeOS-SomeBuild',
                'BuildStamp' => '20180122-0100-Experimental',
                'Name' => 'mirror.site',
            ])
            ->createAuthor(
                'user_1@company.tld',
                ['BuildOne', 'BuildTwo', 'BuildThree', 'BuildFour', 'BuildFive']
            )
            ->createAuthor(
                'user_4@company.tld',
                ['BuildOne', 'BuildTwo', 'BuildThree', 'BuildFour', 'BuildFive']
            )
            ->createAuthor(
                'user_6@company.tld',
                ['BuildThree']
            )
            ->createAuthor(
                'user_7@company.tld',
                ['BuildTwo']
            )
            ->createSubproject('BuildOne')
            ->createSubproject('BuildTwo')
            ->createSubproject('BuildThree', ['BuildThree', 'BuildTres'])
            ->createSubproject('BuildFour')
            ->createSubproject('BuildFive')
            ->createTestFailed('test_fail_one', ['BuildOne'])
            ->createTestTimedout('test_timedout_one', ['BuildOne'])
            ->createTestPassed('test_passed_one', ['BuildOne'])
            ->createTestFailed('test_fail_two', ['BuildTwo'])
            ->createTestPassed('test_passed_two', ['BuildTwo'])
            ->createTestFailed('test_fail_three', ['BuildTres'])
            ->createTestTimedout('test_timedout_two', ['BuildTwo'])
            ->createTestFailed('test_fail_four', ['BuildFive'])
            ->setStartTime(1518276772)
            ->setEndTime(1518276773);

        $subscribers = [
            [ // This user should receive email
                'user_1@company.tld',
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_TEST
            ],
            [ // This user should *not* receive email, as BuildGroup is Experimental
                'user_2@company.tld',
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_NIGHTLY_SECTION |
                BitmaskNotificationPreferences::EMAIL_TEST
            ],
            [ // This user should *not* receive email, not subscribed to Test
                'user_3@company.tld',
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_CONFIGURE |
                BitmaskNotificationPreferences::EMAIL_DYNAMIC_ANALYSIS |
                BitmaskNotificationPreferences::EMAIL_ERROR |
                BitmaskNotificationPreferences::EMAIL_UPDATE |
                BitmaskNotificationPreferences::EMAIL_WARNING
            ],
            [ // This user should receive an email, the user is the author
                'user_4@company.tld',
                BitmaskNotificationPreferences::EMAIL_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_TEST
            ],
            [ // This user should *not* receive an email, not the author
                'user_5@company.tld',
                BitmaskNotificationPreferences::EMAIL_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_TEST
            ],
            [ // This user should receive an email, the user is subscribed to BuildTres
                'user_6@company.tld',
                BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS |
                BitmaskNotificationPreferences::EMAIL_TEST,
                ['BuildTres']
            ],
            [ // This user should *not* receive an email, though subscribed to 'BuildTres' and
              // being an author, the other settings do not warrent an email a) because not
              // subscribed to EMAIL_TEST; b) Not subscribed to other's issues
                'user_7@company.tld',
                BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS |
                BitmaskNotificationPreferences::EMAIL_ERROR |
                BitmaskNotificationPreferences::EMAIL_WARNING |
                BitmaskNotificationPreferences::EMAIL_CONFIGURE |
                BitmaskNotificationPreferences::EMAIL_DYNAMIC_ANALYSIS |
                BitmaskNotificationPreferences::EMAIL_USER_CHECKIN_ISSUE_ANY_SECTION,
                ['BuildTres']
            ],

        ];

        $notifications = $this->getNotifications($subscribers);
        $this->assertCount(3, $notifications);

        $notification = $notifications->get('user_1@company.tld');
        $this->assertInstanceOf(NotificationInterface::class, $notification);

        $notification = $notifications->get('user_4@company.tld');
        $this->assertInstanceOf(NotificationInterface::class, $notification);
    }
}
