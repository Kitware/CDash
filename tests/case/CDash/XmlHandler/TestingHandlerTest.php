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
use CDash\Messaging\Subscription\CommitAuthorSubscriptionBuilder;
use CDash\Messaging\Subscription\UserSubscriptionBuilder;
use CDash\Messaging\Topic\Topic;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Subscriber;

class TestingHandlerTest extends PHPUnit_Framework_TestCase
{
    public function testGetBuildTopic()
    {
        $sut = new TestingHandler(1, 0);

        $preferences = new BitmaskNotificationPreferences();
        $subscriber = new Subscriber($preferences);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);

        $this->assertInstanceOf(TopicCollection::class, $collection);
        $this->assertFalse($collection->hasItems());

        $preferences->set(NotifyOn::TEST_FAILURE, true);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);

        $this->assertCount(2, $collection);
        $this->assertTrue($collection->has(Topic::TEST_FAILURE));
        $this->assertTrue($collection->has(Topic::TEST_MISSING));
    }

    public function testGetSubscriptionBuilderCollection()
    {
        $sut = new TestingHandler(0, 0);
        $collection = $sut->GetSubscriptionBuilderCollection();

        $this->assertCount(2, $collection);
        $this->assertTrue($collection->has(UserSubscriptionBuilder::class));
        $this->assertTrue($collection->has(CommitAuthorSubscriptionBuilder::class));
    }
}
