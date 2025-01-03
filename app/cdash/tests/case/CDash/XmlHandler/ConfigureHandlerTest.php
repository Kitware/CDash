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

use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Project;
use CDash\Model\Subscriber;
use Tests\TestCase;

class ConfigureHandlerTest extends TestCase
{
    public function testGetBuildTopic()
    {
        $project = new Project();
        $project->Id = 1;
        $sut = new ConfigureHandler($project);

        $preferences = new BitmaskNotificationPreferences();
        $subscriber = new Subscriber($preferences);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);

        self::assertCount(0, $collection);

        $preferences->set(NotifyOn::CONFIGURE, true);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);

        self::assertCount(1, $collection);
        $this->assertTrue($collection->has(Topic::CONFIGURE));
    }

    public function testGetSubscriptionBuilderCollection()
    {
        $project = new Project();
        $project->Id = 0;
        $sut = new ConfigureHandler($project);
        $builders = $sut->GetSubscriptionBuilderCollection();

        self::assertCount(1, $builders);
        $this->assertTrue($builders->has(UserSubscriptionBuilder::class));
    }
}
