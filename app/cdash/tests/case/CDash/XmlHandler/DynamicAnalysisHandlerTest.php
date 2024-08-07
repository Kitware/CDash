<?php

use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Messaging\Topic\Topic;
use CDash\Messaging\Topic\TopicCollection;
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
        $sut = new DynamicAnalysisHandler(1, 0);

        $preferences = new BitmaskNotificationPreferences();
        $subscriber = new Subscriber($preferences);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);

        $this->assertInstanceOf(TopicCollection::class, $collection);
        self::assertCount(0, $collection);

        $preferences->set(NotifyOn::DYNAMIC_ANALYSIS, true);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);
        self::assertCount(1, $collection);
        $this->assertTrue($collection->has(Topic::DYNAMIC_ANALYSIS));
    }

    public function testGetSubscriptionBuilderCollection()
    {
        $sut = new DynamicAnalysisHandler(0, 0);
        $builders = $sut->GetSubscriptionBuilderCollection();

        self::assertCount(1, $builders);
        $this->assertTrue($builders->has(UserSubscriptionBuilder::class));
    }
}
