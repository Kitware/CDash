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
use CDash\Messaging\Topic\AuthoredTopic;
use CDash\Messaging\Topic\Decoratable;
use CDash\Messaging\Topic\EmailSentTopic;
use CDash\Messaging\Topic\Fixable;
use CDash\Messaging\Topic\FixedTopic;
use CDash\Messaging\Topic\GroupMembershipTopic;
use CDash\Messaging\Topic\Labelable;
use CDash\Messaging\Topic\LabeledTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Messaging\Topic\TopicDecorator;
use CDash\Messaging\Topic\TopicInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

abstract class MockTopic extends Topic implements Decoratable
{
}
abstract class MockTopicFixable extends MockTopic implements Fixable
{
}
abstract class MockTopicLabelable extends MockTopic implements Labelable
{
}

class TopicDecoratorTest extends TestCase
{
    public function testDecorateGivenFixesEmailPreference(): void
    {
        /** @var TopicInterface|MockObject $mock_topic */
        $mock_topic = $this->getMockTopic('MockTopic');
        $collection = new TopicCollection();
        $collection->add($mock_topic);

        $preferences = new BitmaskNotificationPreferences();
        $preferences
            ->set(NotifyOn::REDUNDANT, true)
            ->set(NotifyOn::FIXED, true);

        TopicDecorator::decorate($collection, $preferences);

        $this->assertNotInstanceOf(FixedTopic::class, $collection->get('MockTopic'));

        $mock_topic = $this->getMockTopicFixable('MockTopic');
        $collection = new TopicCollection();
        $collection->add($mock_topic);

        TopicDecorator::decorate($collection, $preferences);

        $this->assertInstanceOf(FixedTopic::class, $collection->get('MockTopic'));
    }

    public function testDecorateGivenEmailRedundantPreference(): void
    {
        /** @var TopicInterface|MockObject $mock_topic */
        $mock_topic = $this->getMockTopic('MockTopic');
        $collection = new TopicCollection();
        $collection->add($mock_topic);

        $preferences = new BitmaskNotificationPreferences();
        TopicDecorator::decorate($collection, $preferences);

        $this->assertInstanceOf(EmailSentTopic::class, $collection->get('MockTopic'));
    }

    public function testDecorateGivenGroupFilterableEmailPreference(): void
    {
        /** @var TopicInterface|MockObject $mock_topic */
        $mock_topic = $this->getMockTopic('MockTopic');
        $collection = new TopicCollection();
        $collection->add($mock_topic);

        $preferences = new BitmaskNotificationPreferences();

        $preferences
            ->set(NotifyOn::REDUNDANT, true)
            ->set(NotifyOn::GROUP_NIGHTLY, true);

        TopicDecorator::decorate($collection, $preferences);

        $this->assertInstanceOf(GroupMembershipTopic::class, $collection->get('MockTopic'));
    }

    public function testDecorateGivenLabelsEmailPreference(): void
    {
        /** @var TopicInterface|MockObject $mock_topic */
        $mock_topic = $this->getMockTopic('MockTopic');
        $collection = new TopicCollection();
        $collection->add($mock_topic);

        $preferences = new BitmaskNotificationPreferences();
        $preferences
            ->set(NotifyOn::REDUNDANT, true)
            ->set(NotifyOn::LABELED, true);

        TopicDecorator::decorate($collection, $preferences);

        $this->assertNotInstanceOf(LabeledTopic::class, $collection->get('MockTopic'));

        /** @var TopicInterface|MockObject $mock_topic */
        $mock_topic = $this->getMockTopicLabelable('MockTopic');
        $collection = new TopicCollection();
        $collection->add($mock_topic);

        TopicDecorator::decorate($collection, $preferences);

        $this->assertInstanceOf(LabeledTopic::class, $collection->get('MockTopic'));
    }

    public function testDecorateGivenAuthoredEmailPreference(): void
    {
        /** @var TopicInterface|MockObject $mock_topic */
        $mock_topic = $this->getMockTopic('MockTopic');
        $collection = new TopicCollection();
        $collection->add($mock_topic);

        $preferences = new BitmaskNotificationPreferences();

        $preferences
            ->set(NotifyOn::REDUNDANT, true)
            ->set(NotifyOn::AUTHORED, true);

        TopicDecorator::decorate($collection, $preferences);

        $this->assertInstanceOf(AuthoredTopic::class, $collection->get('MockTopic'));

        $mock_topic = $this->getMockTopicLabelable('MockTopic');
        $collection = new TopicCollection();
        $collection->add($mock_topic);

        $preferences->set(NotifyOn::LABELED, true);
        TopicDecorator::decorate($collection, $preferences);

        // Labeled topics should not be decorated with authored topics
        $this->assertNotInstanceOf(AuthoredTopic::class, $collection->get('MockTopic'));
    }

    private function getMockTopic($named)
    {
        $mock_topic = $this->getMockBuilder(MockTopic::class)
            ->onlyMethods(['getTopicName', 'isSubscribedToBy', 'subscribesToBuild'])
            ->getMock();

        $mock_topic->expects($this->any())
            ->method('getTopicName')
            ->willReturn($named);

        return $mock_topic;
    }

    private function getMockTopicFixable($named)
    {
        $mock_topic = $this->getMockBuilder(MockTopicFixable::class)
            ->onlyMethods(['getTopicName', 'isSubscribedToBy', 'subscribesToBuild', 'hasFixes', 'getFixes'])
            ->getMock();

        $mock_topic->expects($this->any())
            ->method('getTopicName')
            ->willReturn($named);

        return $mock_topic;
    }

    private function getMockTopicLabelable($named)
    {
        $mock_topic = $this->getMockBuilder(MockTopicLabelable::class)
            ->onlyMethods(['getTopicName', 'isSubscribedToBy', 'subscribesToBuild', 'getLabelsFromBuild', 'setTopicDataWithLabels'])
            ->getMock();

        $mock_topic->expects($this->any())
            ->method('getTopicName')
            ->willReturn($named);

        return $mock_topic;
    }
}
