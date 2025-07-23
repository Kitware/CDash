<?php

use App\Http\Submission\Handlers\DynamicAnalysisHandler;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Project;
use CDash\Model\Subscriber;
use Tests\TestCase;

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
class DynamicAnalysisHandlerTest extends TestCase
{
    public function testGetBuildTopic()
    {
        $project = new Project();
        $project->Id = 1;
        $sut = new DynamicAnalysisHandler($project);

        $preferences = new BitmaskNotificationPreferences();
        $subscriber = new Subscriber($preferences);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);

        self::assertCount(0, $collection);

        $preferences->set(NotifyOn::DYNAMIC_ANALYSIS, true);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);
        self::assertCount(1, $collection);
        $this->assertTrue($collection->has(Topic::DYNAMIC_ANALYSIS));
    }

    public function testGetSubscriptionBuilderCollection()
    {
        $project = new Project();
        $project->Id = 0;
        $sut = new DynamicAnalysisHandler($project);
        $builders = $sut->GetSubscriptionBuilderCollection();

        self::assertCount(1, $builders);
        $this->assertTrue($builders->has(UserSubscriptionBuilder::class));
    }
}
