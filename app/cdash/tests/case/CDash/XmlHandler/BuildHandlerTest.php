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

use App\Http\Submission\Handlers\BuildHandler;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Subscription\CommitAuthorSubscriptionBuilder;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Project;
use CDash\Model\Subscriber;
use CDash\Submission\CommitAuthorHandlerInterface;
use Tests\TestCase;

class BuildHandlerTest extends TestCase
{
    public function testBuildHandlerIsACommitAuthorHandler()
    {
        $project = new Project();
        $project->Id = 1;
        $sut = new BuildHandler($project);
        $this->assertInstanceOf(CommitAuthorHandlerInterface::class, $sut);
    }

    public function testGetTopicCollectionForSubscriber()
    {
        $project = new Project();
        $project->Id = 1;
        $sut = new BuildHandler($project);
        $preferences = new BitmaskNotificationPreferences();
        $subscriber = new Subscriber($preferences);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);

        // Given the preferences the collection should be empty
        self::assertCount(0, $collection);

        $preferences->set(NotifyOn::BUILD_ERROR, true);
        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);

        // Given the preferences the collection should contain a single BuildErrorTopic
        $this->assertCount(1, $collection);
        $this->assertTrue($collection->has(Topic::BUILD_ERROR));

        $preferences->set(NotifyOn::BUILD_ERROR, false)
            ->set(NotifyOn::BUILD_WARNING, true);

        // Given the preferences the collection should contain a single BuildErrorTopic
        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);
        $this->assertCount(1, $collection);
        $this->assertTrue($collection->has(Topic::BUILD_WARNING));

        $preferences->set(NotifyOn::BUILD_ERROR, true)
            ->set(NotifyOn::BUILD_WARNING, true);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);
        $this->assertCount(2, $collection);
        $this->assertTrue($collection->has(Topic::BUILD_ERROR));
        $this->assertTrue($collection->has(Topic::BUILD_WARNING));
    }

    public function testGetSubscriptionBuilderCollection()
    {
        $project = new Project();
        $project->Id = 0;
        $sut = new BuildHandler($project);
        $builders = $sut->GetSubscriptionBuilderCollection();
        $this->assertCount(2, $builders);
        $this->assertTrue($builders->has(UserSubscriptionBuilder::class));
        $this->assertTrue($builders->has(CommitAuthorSubscriptionBuilder::class));
    }
}
