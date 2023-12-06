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

use CDash\Collection\BuildCollection;
use CDash\Collection\SubscriberCollection;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Subscription\SubscriptionCollection;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Model\BuildGroup;
use CDash\Model\Project;
use App\Models\Site;
use CDash\Model\Subscriber;
use CDash\Test\BuildDiffForTesting;
use Tests\TestCase;

class UserSubscriptionBuilderTest extends TestCase
{
    use BuildDiffForTesting;

    public function testBuildGivenBuildSubmission()
    {
        $buildA = $this->createMockBuildWithDiff($this->createNew('builderrorspositive'));
        $buildB = $this->createMockBuildWithDiff($this->createNew('buildwarningspositive'));

        $buildA->Name = 'BuildA';
        $buildB->Name = 'BuildB';

        $buildCollection = (new BuildCollection)
            ->add($buildA)
            ->add($buildB);

        /** @var BuildHandler|PHPUnit_Framework_MockObject_MockObject $mock_build_submission */
        $mock_build_submission = $this->getMockHandler($buildCollection);

        // Insted of using mocked method return this allows us to use the actual logic
        $mock_build_submission->expects($this->any())
            ->method('GetTopicCollectionForSubscriber')
            ->willReturnCallback(function ($subscriber) {
                $handler = new BuildHandler(0, 0);
                return $handler->GetTopicCollectionForSubscriber($subscriber);
            });

        $subscriptions = new SubscriptionCollection();
        $sut = new UserSubscriptionBuilder($mock_build_submission);
        $sut->build($subscriptions);

        $this->assertCount(2, $subscriptions);
        $this->assertTrue($subscriptions->has('build.error@company.tld'));
        $this->assertTrue($subscriptions->has('build.warning@company.tld'));
    }

    private function getMockHandler(BuildCollection $builds)
    {
        $mock_handler = $this->getMockBuilder(ActionableBuildInterface::class)
            ->getMockForAbstractClass();

        $mock_project = $this->getMockBuilder(Project::class)
            ->onlyMethods(['GetSubscriberCollection'])
            ->getMock();

        $mock_project->expects($this->any())
            ->method('GetSubscriberCollection')
            ->willReturn($this->getProjectSubscriberCollection());

        $mock_site = $this->getMockBuilder(Site::class)
            ->getMock();

        $mock_handler->expects($this->any())
            ->method('GetProject')
            ->willReturn($mock_project);

        $mock_handler->expects($this->any())
            ->method('GetSite')
            ->willReturn($mock_site);

        $mock_handler->expects($this->any())
            ->method('GetBuildCollection')
            ->willReturn($builds);

        $mock_group = $this->getMockBuilder(BuildGroup::class)
            ->getMock();

        $mock_group->expects($this->any())
            ->method('GetName')
            ->willReturn(BuildGroup::EXPERIMENTAL);

        $mock_handler->expects($this->any())
            ->method('GetBuildGroup')
            ->willReturn($mock_group);

        return $mock_handler;
    }

    private function getProjectSubscriberCollection()
    {
        $collection = new SubscriberCollection();
        $collection
            ->add(
                (new Subscriber(
                    (new BitmaskNotificationPreferences)
                        ->set(NotifyOn::BUILD_ERROR, true)
                ))->setAddress('build.error@company.tld')
            )
            ->add(
                (new Subscriber(
                    (new BitmaskNotificationPreferences)
                        ->set(NotifyOn::BUILD_WARNING, true)
                ))->setAddress('build.warning@company.tld')
            )
            ->add(
                (new Subscriber(
                    (new BitmaskNotificationPreferences)
                        ->set(NotifyOn::CONFIGURE, true)
                ))->setAddress('configure.error@company.tld')
            )
            ->add(
                (new Subscriber(
                    (new BitmaskNotificationPreferences)
                        ->set(NotifyOn::DYNAMIC_ANALYSIS, true)
                ))->setAddress('dynamic.analysis@company.tld')
            )
            ->add(
                (new Subscriber(
                    (new BitmaskNotificationPreferences)
                        ->set(NotifyOn::TEST_FAILURE, true)
                ))->setAddress('test.failure@company.tld')
            )
            ->add(
                (new Subscriber(
                    (new BitmaskNotificationPreferences)
                        ->set(NotifyOn::UPDATE_ERROR, true)
                ))->setAddress('update.error@company.tld')
            );
        return $collection;
    }
}
