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
use CDash\Messaging\Topic\EmailSentTopic;
use CDash\Messaging\Topic\GroupMembershipTopic;
use CDash\Messaging\Topic\LabeledTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Messaging\Topic\TopicFactory;
use CDash\Model\UserProject;

class TopicFactoryTest extends PHPUnit_Framework_TestCase
{
    public function testCreateFromReturnsUndecoratedBaseTopicsGivenDefaultPreferences()
    {
        $userProject = new UserProject();
        $preferences = new BitmaskNotificationPreferences($userProject->EmailCategory);
        // prevent our base topics from being decorated by EmailSentTopic
        $preferences->set(NotifyOn::REDUNDANT, true);
        $topics = TopicFactory::createFrom($preferences);

        $this->assertInternalType('array', $topics);
        $this->assertContainsOnlyInstancesOf(Topic::class, $topics);

        $expected = [
            'BuildError',
            'BuildWarning',
            'Configure',
            'DynamicAnalysis',
            'TestFailure',
            'UpdateError',
        ];

        $actual = array_map(
            function (Topic $topic) {
                return $topic->getTopicName();
            },
            $topics
        );

        sort($actual);

        $this->assertEquals($expected, $actual);
    }

    public function testCreateFromReturnsTopicsDecoratedWithEmailSentGivenRedundantPreferenceFalse()
    {
        $userProject = new UserProject();
        $preferences = new BitmaskNotificationPreferences($userProject->EmailCategory);
        $preferences->set(NotifyOn::REDUNDANT, false);
        $topics = TopicFactory::createFrom($preferences);

        $this->assertContainsOnlyInstancesOf(EmailSentTopic::class, $topics);
    }

    public function testCreateFromReturnsTopicsDecoratedWithGroupMembershipGivenGroupPreferenceTrue()
    {
        $userProject = new UserProject();
        $preferences = new BitmaskNotificationPreferences($userProject->EmailCategory);
        $preferences->set(NotifyOn::GROUP_NIGHTLY, true);
        $topics = TopicFactory::createFrom($preferences);

        $this->assertContainsOnlyInstancesOf(GroupMembershipTopic::class, $topics);
    }

    public function testCreateFromReturnsTopicsDecoratedWithAuthoredGivenAuthoredPreferenceTrue()
    {
        $userProject = new UserProject();
        $preferences = new BitmaskNotificationPreferences($userProject->EmailCategory);
        $preferences->set(NotifyOn::AUTHORED, true);
        $topics = TopicFactory::createFrom($preferences);

        $this->assertContainsOnlyInstancesOf(AuthoredTopic::class, $topics);
    }

    public function testCreateFromReturnsLabeledTopicNotDecoratedByAuthoredTopic()
    {
        $userProject = new UserProject();
        $preferences = new BitmaskNotificationPreferences($userProject->EmailCategory);
        $preferences->set(NotifyOn::REDUNDANT, true);
        $preferences->set(NotifyOn::AUTHORED, true);
        $preferences->set(NotifyOn::LABELED, true);

        $topics = TopicFactory::createFrom($preferences);

        $expected = [
            AuthoredTopic::class,
            LabeledTopic::class
        ];

        $actual = array_map(
            function ($topic) {
                return get_class($topic);
            },
            $topics
        );

        $actual = array_unique($actual);

        sort($actual);

        $this->assertEquals($expected, $actual);
    }
}
