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

abstract class MockTopic extends Topic implements Decoratable
{
};
abstract class MockTopicFixable extends MockTopic implements Fixable
{
};
abstract class MockTopicLabelable extends MockTopic implements Labelable
{
};

class TopicDecoratorTest extends PHPUnit_Framework_TestCase
{
    public function testDecorateGivenFixesEmailPreference()
    {
        /** @var TopicInterface|PHPUnit_Framework_MockObject_MockObject $mock_topic */
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

    public function testDecorateGivenEmailRedundantPreference()
    {
        /** @var TopicInterface|PHPUnit_Framework_MockObject_MockObject $mock_topic */
        $mock_topic = $this->getMockTopic('MockTopic');
        $collection = new TopicCollection();
        $collection->add($mock_topic);

        $preferences = new BitmaskNotificationPreferences();
        TopicDecorator::decorate($collection, $preferences);

        $this->assertInstanceOf(EmailSentTopic::class, $collection->get('MockTopic'));
    }

    public function testDecorateGivenGroupFilterableEmailPreference()
    {
        /** @var TopicInterface|PHPUnit_Framework_MockObject_MockObject $mock_topic */
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

    public function testDecorateGivenLabelsEmailPreference()
    {
        /** @var TopicInterface|PHPUnit_Framework_MockObject_MockObject $mock_topic */
        $mock_topic = $this->getMockTopic('MockTopic');
        $collection = new TopicCollection();
        $collection->add($mock_topic);

        $preferences = new BitmaskNotificationPreferences();
        $preferences
            ->set(NotifyOn::REDUNDANT, true)
            ->set(NotifyOn::LABELED, true);

        TopicDecorator::decorate($collection, $preferences);

        $this->assertNotInstanceOf(LabeledTopic::class, $collection->get('MockTopic'));

        /** @var TopicInterface|PHPUnit_Framework_MockObject_MockObject $mock_topic */
        $mock_topic = $this->getMockTopicLabelable('MockTopic');
        $collection = new TopicCollection();
        $collection->add($mock_topic);

        TopicDecorator::decorate($collection, $preferences);

        $this->assertInstanceOf(LabeledTopic::class, $collection->get('MockTopic'));
    }

    public function testDecorateGivenAuthoredEmailPreference()
    {
        /** @var TopicInterface|PHPUnit_Framework_MockObject_MockObject $mock_topic */
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

    /**
     * @param $name
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockTopic($named)
    {
        $mock_topic = $this->getMockForAbstractClass(
            MockTopic::class,
            [],
            '',
            true,
            true,
            true,
            ['getTopicName']
        );

        $mock_topic->expects($this->any())
            ->method('getTopicName')
            ->willReturn($named);

        return $mock_topic;
    }

    private function getMockTopicFixable($named)
    {
        $mock_topic = $this->getMockForAbstractClass(
            MockTopicFixable::class,
            [],
            '',
            true,
            true,
            true,
            ['getTopicName']
        );

        $mock_topic->expects($this->any())
            ->method('getTopicName')
            ->willReturn($named);

        return $mock_topic;
    }

    private function getMockTopicLabelable($named)
    {
        $mock_topic = $this->getMockForAbstractClass(
            MockTopicLabelable::class,
            [],
            '',
            true,
            true,
            true,
            ['getTopicName']
        );

        $mock_topic->expects($this->any())
            ->method('getTopicName')
            ->willReturn($named);

        return $mock_topic;
    }
}
