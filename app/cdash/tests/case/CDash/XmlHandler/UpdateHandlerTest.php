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
use CDash\Submission\CommitAuthorHandlerInterface;
use CDash\Test\CDashTestCase;

class UpdateHandlerTest extends CDashTestCase
{
    public function testUpdateHandlerIsACommitAuthorHandler()
    {
        $sut = new UpdateHandler(0, 0);
        $this->assertInstanceOf(CommitAuthorHandlerInterface::class, $sut);
    }

    public function testGetBuildTopic()
    {
        $sut = new UpdateHandler(1, 0);

        $preferences = new BitmaskNotificationPreferences();
        $subscriber = new Subscriber($preferences);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);

        $this->assertInstanceOf(TopicCollection::class, $collection);
        self::assertCount(0, $collection);

        $preferences->set(NotifyOn::UPDATE_ERROR, true);

        $collection = $sut->GetTopicCollectionForSubscriber($subscriber);

        self::assertCount(1, $collection);
        $this->assertTrue($collection->has(Topic::UPDATE_ERROR));
    }

    public function testGetSubscriptionBuilderCollection()
    {
        $sut = new UpdateHandler(0, 0);
        $collection = $sut->GetSubscriptionBuilderCollection();

        self::assertCount(2, $collection);
        $this->assertTrue($collection->has(UserSubscriptionBuilder::class));
        $this->assertTrue($collection->has(CommitAuthorSubscriptionBuilder::class));
    }
}
