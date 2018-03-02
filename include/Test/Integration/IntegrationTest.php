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
                ['BuildTwo', 'BuildFive']
            )
            ->createAuthor(
                'user_6@company.tld',
                ['BuildFour']
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
              BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS |
              BitmaskNotificationPreferences::EMAIL_TEST,
                ['BuildTres']
            ],
            [ // This user should *not* receive email, as BuildGroup is Experimental
                'user_2@company.tld',
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_NIGHTLY_SECTION |
                BitmaskNotificationPreferences::EMAIL_TEST
            ],
            [ // This user should *not* receive email, not subscribed to Test
                'user_3@company.tld',
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
            [ // This user should receive an email, the user is subscribed to BuildTres and
              // test failures, of which BuildTres has
                'user_6@company.tld',
                BitmaskNotificationPreferences::EMAIL_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS |
                BitmaskNotificationPreferences::EMAIL_TEST,
                ['BuildTres']
            ],
        ];

        $notifications = $this->getNotifications($subscribers);
        $this->assertCount(3, $notifications);

        $notification = $notifications->get('user_1@company.tld');
        $this->assertInstanceOf(NotificationInterface::class, $notification);

        $body = [
            'A submission to CDash for the project CDashUseCaseProject has failing tests. You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.',
            '',
            'Details on the submission can be found at /CDash/viewProject?projectid=321',
            '',
            'Project: CDashUseCaseProject',
            'Site: mirror.site',
            'Build Name: SomeOS-SomeBuild',
            'Build Time: 2018-02-10T15:32:52',
            'Type: Experimental',
            'Total Failing Tests: 6',
            '',
            '',
            '*Tests Failing* (first 5 included)',
            'test_fail_one | Completed (Failed) | (http://open.cdash.org/testDetails.php?test=&build=)',
            'test_timedout_one | Completed (Timeout) | (http://open.cdash.org/testDetails.php?test=&build=)',
            'test_fail_two | Completed (Failed) | (http://open.cdash.org/testDetails.php?test=&build=)',
            'test_timedout_two | Completed (Timeout) | (http://open.cdash.org/testDetails.php?test=&build=)',
            'test_fail_three | Completed (Failed) | (http://open.cdash.org/testDetails.php?test=&build=)',
            '',
            '',
            '-CDash on open.cdash.org',
            '',
        ];

        $expected = implode("\n", $body);
        $actual = "{$notification}";
        $this->assertEquals($expected, $actual);
        $expected = 'FAILED (t=6) CDashUseCaseProject - SomeOS-SomeBuild - Experimental';
        $actual = $notification->getSubject();
        $this->assertEquals($expected, $actual);

        $notification = $notifications->get('user_4@company.tld');
        $this->assertInstanceOf(NotificationInterface::class, $notification);
        $body = [
            'A submission to CDash for the project CDashUseCaseProject has failing tests. You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.',
            '',
            'Details on the submission can be found at /CDash/viewProject?projectid=321',
            '',
            'Project: CDashUseCaseProject',
            'Site: mirror.site',
            'Build Name: SomeOS-SomeBuild',
            'Build Time: 2018-02-10T15:32:52',
            'Type: Experimental',
            'Total Failing Tests: 3',
            '',
            '',
            '*Tests Failing*',
            'test_fail_two | Completed (Failed) | (http://open.cdash.org/testDetails.php?test=&build=)',
            'test_timedout_two | Completed (Timeout) | (http://open.cdash.org/testDetails.php?test=&build=)',
            'test_fail_four | Completed (Failed) | (http://open.cdash.org/testDetails.php?test=&build=)',
            '',
            '',
            '-CDash on open.cdash.org',
            '',
        ];

        $expected = implode("\n", $body);
        $actual = "{$notification}";
        $this->assertEquals($expected, $actual);
        $expected = 'FAILED (t=3) CDashUseCaseProject - SomeOS-SomeBuild - Experimental';
        $actual = $notification->getSubject();
        $this->assertEquals($expected, $actual);

        $notification = $notifications->get('user_6@company.tld');
        $body = [
            'A submission to CDash for the project CDashUseCaseProject has failing tests. You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.',
            '',
            'Details on the submission can be found at /CDash/viewProject?projectid=321',
            '',
            'Project: CDashUseCaseProject',
            'Site: mirror.site',
            'Build Name: SomeOS-SomeBuild',
            'Build Time: 2018-02-10T15:32:52',
            'Type: Experimental',
            'Total Failing Tests: 1',
            '',
            '',
            '*Tests Failing*',
            'test_fail_three | Completed (Failed) | (http://open.cdash.org/testDetails.php?test=&build=)',
            '',
            '',
            '-CDash on open.cdash.org',
            '',
        ];
        $expected = implode("\n", $body);
        $actual = "{$notification}";
        $this->assertEquals($expected, $actual);
        $expected = 'FAILED (t=1) CDashUseCaseProject/BuildTres - SomeOS-SomeBuild - Experimental';
        $actual = $notification->getSubject();
        $this->assertEquals($expected, $actual);
    }
}
