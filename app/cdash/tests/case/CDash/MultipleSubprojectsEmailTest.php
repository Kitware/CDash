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

use CDash\Collection\SubscriberCollection;
use CDash\Database;
use CDash\Messaging\Notification\Email\EmailBuilder;
use CDash\Messaging\Notification\Email\EmailNotificationFactory;
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

class MultipleSubprojectsEmailTest extends CDashUseCaseTestCase
{
    private static $tz;
    private static $database;

    private static int $projectid = -1;

    /** @var  Database|PHPUnit_Framework_MockObject_MockObject $db */
    private $db;

    /** @var  ActionableBuildInterface $submission */
    private $submission;

    /** @var UseCase $useCase */
    private $useCase;

    public static function setUpBeforeClass() : void
    {
        parent::setUpBeforeClass();

        // deal with timezone stuff
        self::$tz = date_default_timezone_get();

        // so that we can mock the database layer
        self::$database = Database::getInstance();
    }

    public static function tearDownAfterClass() : void
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

    public function setUp() : void
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
     * @param array $subscribers
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

        /** @var EmailBuilder $builder */
        $builder = new EmailBuilder(new EmailNotificationFactory(), new NotificationCollection());
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
}
