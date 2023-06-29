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
use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Subscriber;
use Tests\TestCase;

class ConfigureHandlerTest extends TestCase
{
    public function testGetBuildTopic()
    {
        $sut = new ConfigureHandler(1, 0);

        $preferences = new BitmaskNotificationPreferences();
        $subscriber = new Subscriber($preferences);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);

        $this->assertInstanceOf(TopicCollection::class, $collection);
        $this->assertFalse($collection->hasItems());

        $preferences->set(NotifyOn::CONFIGURE, true);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);

        $this->assertCount(1, $collection);
        $this->assertTrue($collection->has(Topic::CONFIGURE));
    }

    public function testGetSubscriptionBuilderCollection()
    {
        $sut = new ConfigureHandler(0, 0);
        $builders = $sut->GetSubscriptionBuilderCollection();

        $this->assertCount(1, $builders);
        $this->assertTrue($builders->has(UserSubscriptionBuilder::class));
    }
}
