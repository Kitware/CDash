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

use CDash\Collection\BuildEmailCollection;
use CDash\Collection\CollectionCollection;
use CDash\Collection\CollectionCollectioæn;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Topic\EmailSentTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Model\ActionableTypes;
use CDash\Model\Build;
use CDash\Model\BuildEmail;
use CDash\Model\Subscriber;

class EmailSentTest extends \CDash\Test\CDashTestCase
{
    public function testSubscribesToBuildGivenDecoratedTopicDoesNotSubscribe()
    {
        $mock_topic = $this->getAbstractMockTopic(false);

        $sut = new EmailSentTopic($mock_topic);

        $this->assertFalse($sut->subscribesToBuild(new Build()));
    }

    public function testSubscribesToBuildGivenNotificationSentStatus()
    {
        $mock_topic = $this->getAbstractMockTopic(true);
        $category = ActionableTypes::$categories[$mock_topic->getTopicName()];

        $subscriber = $this->createSubscriber();

        $buildEmailCollection = new BuildEmailCollection();

        // We could use a non-mocked build here but we want to ensure that
        // the path we think is being taken is actually taken so we mock
        // the build here so that we can verify that the GetBuildEmailCollection
        // method is actually called
        $build = $this->getMockBuild();
        $build->expects($this->exactly(2))
            ->method('GetBuildEmailCollection')
            ->with($category)
            ->willReturn($buildEmailCollection);

        $sut = new EmailSentTopic($mock_topic);
        $sut->setSubscriber($subscriber);

        $this->assertTrue($sut->subscribesToBuild($build));

        // Add the BuildEmail to the collection and we should no longer be subscribed
        $buildEmailCollection->addItem(new BuildEmail(), $subscriber->getAddress());

        $this->assertFalse($sut->subscribesToBuild($build));
    }

    public function testSubscribesToBuildGivenRedundantPreferenceSettings()
    {
        $mock_topic = $this->getAbstractMockTopic(true);

        $subscriber = $this->createSubscriber(1);

        // We could use a non-mocked build here but we want to ensure that
        // the path we think is being taken is actually taken so we mock
        // the build here so that we can verify that the GetBuildEmailCollection
        // method is never called
        $build = $this->getMockBuild();
        $build->expects($this->never())
            ->method('GetBuildEmailCollection');

        $sut = new EmailSentTopic($mock_topic);
        $sut->setSubscriber($subscriber);

        $this->assertTrue($sut->subscribesToBuild($build));
    }

    /**
     * @param $subscribesToBuild
     * @return Topic|PHPUnit_Framework_MockObject_MockObject
     */
    private function getAbstractMockTopic($subscribesToBuild)
    {
        $mock_topic = $this->getMockForAbstractClass(
            Topic::class,
            [],
            '',
            false,
            true,
            true,
            ['subscribesToBuild', 'getTopicName']);

        $mock_topic->expects($this->any())
            ->method('subscribesToBuild')
            ->willReturn($subscribesToBuild);

        $mock_topic->expects($this->any())
            ->method('getTopicName')
            ->willReturn(ActionableTypes::TEST);

        return $mock_topic;
    }

    private function createSubscriber($redundant = 0)
    {
        $preferences = new BitmaskNotificationPreferences(128);
        $preferences->setEmailRedundantMessages($redundant);

        $subscriber = new Subscriber($preferences);
        $subscriber->setAddress('cdash.user@company.tld');
        return $subscriber;
    }
}
