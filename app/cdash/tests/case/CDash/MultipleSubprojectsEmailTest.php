<?php

/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

use App\Http\Submission\Handlers\ActionableBuildInterface;
use CDash\Collection\SubscriberCollection;
use CDash\Database;
use CDash\Messaging\Notification\Email\EmailBuilder;
use CDash\Messaging\Notification\NotificationCollection;
use CDash\Messaging\Notification\NotificationDirector;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Subscription\SubscriptionCollection;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Model\Label;
use CDash\Model\Subscriber;
use CDash\Test\CDashUseCaseTestCase;
use CDash\Test\UseCase\UseCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PHPUnit\Framework\MockObject\MockObject;

class MultipleSubprojectsEmailTest extends CDashUseCaseTestCase
{
    private static $tz;
    private static $database;

    private static int $projectid = -1;

    /** @var Database|MockObject */
    private $db;

    /** @var ActionableBuildInterface */
    private $submission;

    /** @var UseCase */
    private $useCase;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // deal with timezone stuff
        self::$tz = date_default_timezone_get();

        // so that we can mock the database layer
        self::$database = Database::getInstance();
    }

    public static function tearDownAfterClass(): void
    {
        // restore state
        date_default_timezone_set(self::$tz);
        Database::setInstance(Database::class, self::$database);

        DB::delete('DELETE FROM project WHERE id = ?', [self::$projectid]);

        // Clean up all of the placeholder builds we created
        for ($i = 0; $i < 10; $i++) {
            DB::table('build')->where('name', '=', 'TestBuild' . $i)->delete();
        }

        parent::tearDownAfterClass();
    }

    public function setUp(): void
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

        $this->createApplication();
        config(['app.url' => 'http://open.cdash.org']);
        URL::forceRootUrl('http://open.cdash.org');

        if (self::$projectid === -1) {
            self::$projectid = DB::table('project')->insertGetId([
                'name' => 'TestProject1',
            ]);

            // A hack to make sure builds exist and can be referenced so we don't violate our foreign key constraints
            for ($i = 0; $i < 10; $i++) {
                DB::table('build')->insertOrIgnore([
                    'projectid' => self::$projectid,
                    'name' => 'TestBuild' . $i,
                    'uuid' => 'TestBuild' . $i,
                ]);
            }

            // Do the same for build groups
            DB::table('buildgroup')->insertOrIgnore([
                'id' => 0,
                'projectid' => self::$projectid,
                'description' => 'MultipleSubprojectsEmailTest-' . Str::uuid()->toString(),
            ]);
        }
    }

    /**
     * @return NotificationCollection
     */
    private function getNotifications(array $subscribers)
    {
        $this->submission = $this->useCase->build();
        $project = $this->submission->GetProject();
        $project->EmailMaxItems = 5;
        $project->EmailMaxChars = 255;
        $project->Name = 'CDashUseCaseProject';

        $subscriberCollection = new SubscriberCollection();

        foreach ($subscribers as $entry) {
            $email = $entry[0];
            $settings = $entry[1];
            $labels = [];
            if (isset($entry[2])) {
                $labels = array_map(function ($text) {
                    $label = new Label();
                    $label->Text = $text;
                    return $label;
                }, $entry[2]);
            }

            $preferences = new BitmaskNotificationPreferences($settings);
            $subscriber = new Subscriber($preferences);
            $subscriber
                ->setAddress($email)
                ->setLabels($labels);
            $subscriberCollection->add($subscriber);
        }

        $project->SetSubscriberCollection($subscriberCollection);

        $builder = new UserSubscriptionBuilder($this->submission);
        $subscriptions = new SubscriptionCollection();
        $builder->build($subscriptions);
        $director = new NotificationDirector();

        $builder = new EmailBuilder(new NotificationCollection());
        $builder
            ->setSubscriptions($subscriptions);

        return $director->build($builder);
    }

    public function testMultipleSubprojectsTestSubmission()
    {
        $this->useCase = UseCase::createBuilder($this, UseCase::TEST)
            ->setProjectId(self::$projectid)
            ->createSite([
                'BuildName' => 'CTestTest-Linux-c++-Subprojects',
                'BuildStamp' => '20160728-1932-Experimental',
                'Name' => 'livonia-linux',
            ])
            ->createAuthor('nox-noemail@noemail', ['MyExperimentalFeature'])
            ->createAuthor('optika-noemail@noemail', ['MyThirdPartyDependency'])
            ->createSubproject('MyExperimentalFeature')
            ->createSubproject('MyProductionCode')
            ->createSubproject('MyThirdPartyDependency')
            ->createSubproject('EmptySubproject')
            ->createTestFailed('thirdparty', ['MyThirdPartyDependency'])
            ->createTestFailed('experimentalFail1', ['MyExperimentalFeature'])
            ->createTestFailed('experimentalFail2', ['MyExperimentalFeature'])
            ->createTestFailed('experimentalFail3', ['MyExperimentalFeature'])
            ->createTestFailed('experimentalFail4', ['MyExperimentalFeature'])
            ->createTestFailed('experimentalFail5', ['MyExperimentalFeature'])
            ->createTestPassed('production', ['MyProductionCode']);

        $subscribers = [
            [
                'simpletest@localhost',
                BitmaskNotificationPreferences::EMAIL_TEST |
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION,
            ],
            [
                'nox-noemail@noemail',
                BitmaskNotificationPreferences::EMAIL_TEST |
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS,
                ['NOX', 'MyExperimentalFeature'],
            ],
            [
                'optika-noemail@noemail',
                BitmaskNotificationPreferences::EMAIL_TEST |
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS,
                ['Optika', 'MyThirdPartyDependency'],
            ],
            [
                'trop-noemail@noemail',
                BitmaskNotificationPreferences::EMAIL_TEST |
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS,
                ['RTOp'],
            ],
        ];

        $notifications = $this->getNotifications($subscribers);
        $this->assertCount(3, $notifications);

        $this->assertTrue($notifications->has('simpletest@localhost'));
        $this->assertTrue($notifications->has('nox-noemail@noemail'));
        $this->assertTrue($notifications->has('optika-noemail@noemail'));
    }

    public function testMultipleSubprojectsConfigureSubmission()
    {
        $this->useCase = UseCase::createBuilder($this, UseCase::CONFIG)
            ->createSite([
                'BuildName' => 'CTestTest-Linux-c++-Subprojects',
                'BuildStamp' => '20160728-1932-Experimental',
                'Name' => 'livonia-linux',
            ])
            ->createAuthor('nox-noemail@noemail', ['MyExperimentalFeature'])
            ->createAuthor('optika-noemail@noemail', ['MyThirdPartyDependency'])
            ->createSubproject('MyExperimentalFeature')
            ->createSubproject('MyProductionCode')
            ->createSubproject('MyThirdPartyDependency')
            ->createSubproject('EmptySubproject')
            ->setConfigureStatus(1)
            ->setConfigureLog(implode(PHP_EOL, [
                '-- The C compiler identification is GNU 4.8.4',
                '-- The CXX compiler identification is GNU 4.8.4',
                '-- Check for working C compiler: /usr/bin/cc',
                '-- Check for working C compiler: /usr/bin/cc -- works',
                '-- Detecting C compiler ABI info',
                '-- Detecting C compiler ABI info - done',
                '-- Detecting C compile features',
                '-- Detecting C compile features - done',
                '-- Check for working CXX compiler: /usr/bin/c++',
                '-- Check for working CXX compiler: /usr/bin/c++ -- works',
                '-- Detecting CXX compiler ABI info',
                '-- Detecting CXX compiler ABI info - done',
                '-- Detecting CXX compile features',
                '-- Detecting CXX compile features - done',
                '-- Configuring done',
                '-- Generating done',
                '-- Build files have been written to: /home/betsy/cmake_build/Tests/CTestTestSubprojects',
                'CMake Warning: CMake is forcing CMAKE_CXX_COMPILER to "/usr/bin/c++" to match that imported from VTK.  This is required because C++ projects must use the same compiler.  If this message appears for more than one imported project, you have conflicting C++ compilers and will have to re-build one of those projects. Was set to /usr/bin/g++-4.0',
                'Fltk resources not found, GUI application will not respond to mouse events',
                'Error: example error #1',
            ]));

        $subscribers = [
            [
                'simpletest@localhost',
                BitmaskNotificationPreferences::EMAIL_CONFIGURE |
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION,
            ],
            [
                'nox-noemail@noemail',
                BitmaskNotificationPreferences::EMAIL_CONFIGURE |
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS,
                ['NOX', 'MyExperimentalFeature'],
            ],
            [
                'optika-noemail@noemail',
                BitmaskNotificationPreferences::EMAIL_CONFIGURE |
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS,
                ['Optika', 'MyThirdPartyDependency'],
            ],
            [
                'trop-noemail@noemail',
                BitmaskNotificationPreferences::EMAIL_CONFIGURE |
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS,
                ['RTOp'],
            ],
        ];

        $notifications = $this->getNotifications($subscribers);
        $this->assertCount(3, $notifications);

        $this->assertTrue($notifications->has('simpletest@localhost'));
        $this->assertTrue($notifications->has('nox-noemail@noemail'));
        $this->assertTrue($notifications->has('optika-noemail@noemail'));
    }

    public function testBuildUseCase()
    {
        $this->useCase = UseCase::createBuilder($this, UseCase::BUILD)
            ->createSite([
                'BuildName' => 'CTestTest-Linux-c++-Subprojects',
                'BuildStamp' => '20160728-1932-Experimental',
                'Name' => 'livonia-linux',
            ])
            ->createAuthor('nox-noemail@noemail', ['MyExperimentalFeature'])
            ->createAuthor('optika-noemail@noemail', ['MyThirdPartyDependency'])
            ->createSubproject('MyExperimentalFeature')
            ->createSubproject('MyProductionCode')
            ->createSubproject('MyThirdPartyDependency')
            ->createSubproject('EmptySubproject')
            ->createBuildFailureError('MyThirdPartyDependency')
            ->createBuildFailureError('MyThirdPartyDependency')
            ->createBuildFailureWarning('MyExperimentalFeature')
            ->createBuildFailureWarning('MyProductionCode');

        $subscribers = [
            [
                'simpletest@localhost',
                BitmaskNotificationPreferences::EMAIL_ERROR |
                BitmaskNotificationPreferences::EMAIL_WARNING |
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION,
            ],
            [
                'nox-noemail@noemail',
                BitmaskNotificationPreferences::EMAIL_ERROR |
                BitmaskNotificationPreferences::EMAIL_WARNING |
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS,
                ['NOX', 'MyExperimentalFeature'],
            ],
            [
                'optika-noemail@noemail',
                BitmaskNotificationPreferences::EMAIL_ERROR |
                BitmaskNotificationPreferences::EMAIL_WARNING |
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS,
                ['Optika', 'MyThirdPartyDependency'],
            ],
            [
                'trop-noemail@noemail',
                BitmaskNotificationPreferences::EMAIL_ERROR |
                BitmaskNotificationPreferences::EMAIL_WARNING |
                BitmaskNotificationPreferences::EMAIL_ANY_USER_CHECKIN_ISSUE_ANY_SECTION |
                BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS,
                ['RTOp'],
            ],
        ];

        $notifications = $this->getNotifications($subscribers);
        $this->assertCount(3, $notifications);

        $this->assertTrue($notifications->has('simpletest@localhost'));
        $this->assertTrue($notifications->has('nox-noemail@noemail'));
        $this->assertTrue($notifications->has('optika-noemail@noemail'));
    }

    public function testDyanamicAnalysisUseCaseBuild()
    {
        // TODO: figure out why the time in use case is being set with UTC
        date_default_timezone_set('UTC');
        $start = strtotime('-10 minutes');
        $end = time();
        $datetime = date('Y-m-d\TH:i:s', $start);

        $this->useCase = UseCase::createBuilder($this, UseCase::DYNAMIC_ANALYSIS);
        $this->useCase
            ->createSite([
                'Name' => 'Site.name',
                'BuildName' => 'CTestTest-Linux-c++-Subprojects',
                'BuildStamp' => '20160728-1932-Experimental',
                'Generator' => 'ctest-3.6.20160726-g3e55f-dirty',
            ])
            ->createSubproject('MyExperimentalFeature')
            ->createSubproject('MyProductionCode')
            ->createSubproject('MyThirdPartyDependency')
            ->setChecker('/usr/bin/valgrind')
            ->createFailedTest('experimentalFail', ['Labels' => ['MyExperimentalFeature']])
            ->createPassedTest(
                'thirdparty',
                ['Labels' => ['MyThirdPartyDependency', 'NotASubproject'],
                ]
            )
            ->setStartTime($start)
            ->setEndTime($end);

        $subscribers = [
            [ // This user should receive email
                'user_1@company.tld',
                BitmaskNotificationPreferences::EMAIL_DYNAMIC_ANALYSIS,
                [],
            ],
            [
                // This user should not receive an email as they are not an author
                'user_2@company.tld',
                BitmaskNotificationPreferences::EMAIL_WARNING |
                BitmaskNotificationPreferences::EMAIL_ERROR |
                BitmaskNotificationPreferences::EMAIL_USER_CHECKIN_ISSUE_ANY_SECTION,
                [],
            ],
            [
                // This user should receive an email, subscribed to two labels, one present
                'user_3@company.tld',
                BitmaskNotificationPreferences::EMAIL_SUBSCRIBED_LABELS,
                ['MyThirdPartyDependency1', 'MyProductionCode'],
            ],
            [
                // This user should receive an email, with MyExperimentalFeature appended to subject
                'user_4@company.tld',
                BitmaskNotificationPreferences::EMAIL_ERROR |
                BitmaskNotificationPreferences::EMAIL_USER_CHECKIN_ISSUE_ANY_SECTION,
                [],
            ],
            [
                'user_5@company.tld',
                BitmaskNotificationPreferences::EMAIL_USER_CHECKIN_ISSUE_ANY_SECTION,
                [],
            ],
        ];

        $notifications = $this->getNotifications($subscribers);
        $notification = $notifications->get('user_1@company.tld');

        $this->assertNotNull($notification);
        $this->assertCount(1, $notifications);

        $expected = "A submission to CDash for the project CDashUseCaseProject has dynamic analysis tests failing or not run. You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.

Details on the submission can be found at http://open.cdash.org/build/1.

Project: CDashUseCaseProject
SubProject: MyExperimentalFeature
Site: Site.name
Build Name: CTestTest-Linux-c++-Subprojects
Build Time: {$datetime}
Type: Experimental
Total Dynamic analysis tests failing or not run: 1

*Dynamic analysis tests failing or not run* " . /* Join is needed to preserve trailing space */ '
experimentalFail (http://open.cdash.org/viewDynamicAnalysisFile.php?id=1)

-CDash';
        $actual = "{$notification}";
        $this->assertEquals($expected, $actual);
    }
}
