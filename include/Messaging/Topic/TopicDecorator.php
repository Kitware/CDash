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
namespace CDash\Messaging\Topic;

use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Singleton;

/**
 * Class TopicFactory
 * @package CDash\Messaging\Topic
 *
 * Given NotificationPreferences and an ActionableBuildInterface TopicFactory::createFrom will
 * return an array of decorated topics.
 *
 * Topics come in two flavors--regular topics and decorate-able topics. What follows is a
 * discussion of the distinction.
 *
 * Decorate-able topics can be thought of as fundamental topics, e.g. DynamicAnalysisTopic,
 * BuildErrorTopic, or TestFailureTopic and they represent the type of build (that is, a phase
 * in the build process such as the dynamic analysis, the actual build itself or testing)
 * represented by the xml submitted to CDash by CTest. If a user wants to receive a notification
 * about a test failure the return of this method will include a TestFailureTopic.
 *
 * Note that a TestFailureTopic is decorate-able. Consider the other preferences that may be
 * present in addition to the test failure:
 *   * A user may want to receive notification of test failures only once.
 *   * A user may want to receive notifications of test failures only if user is the author of a
 *     change that prompted the failure.
 *   * A user may not want to receive multiple notifications of test failure, yet receive a
 *     notification when the test has been fixed.
 *
 * At any given time, it's possible for any combination of these preferences to co-exist, thus the
 * reason for the distinction between regular and decorate-able topics. For instance given the
 * following scenario:
 *
 *   * A user is subscribed to DynamicAnalysis submissions
 *   * The user only wishes to see those DynamicAnalysis submissions from the Nightly submissions
 *   * The user wishes to see all DynamicAnalysis submissions regardless of authorship
 *
 * Included in the return from this method will be a DynamicAnalysisTopic decorated by a
 * GroupMemberShipTopic.
 *
 * In another scenario:
 *
 *   * A user is subscribed to test phase submissions with failures
 *   * User only wishes to see failures authored by his or herself
 *   * User does not wish to receive further notifications after the original
 *   * User does wish to receive a notification authored failure was fixed
 *
 * Included in the return from this method will be a TestFailureTopic decorated with an Authored-
 * Topic, an EmailSentTopic, FixedTopic.
 */
class TopicDecorator extends Singleton
{
    /**
     * @param TopicCollection $topics
     * @param NotificationPreferences $preferences
     */
    public static function decorate(TopicCollection $topics, NotificationPreferences $preferences)
    {
        $self = self::getInstance();

        $self->setFixables($preferences, $topics);
        $self->setLabelables($preferences, $topics);
        $self->setDeliverables($preferences, $topics);
        $self->setGroupFilterables($preferences, $topics);
        $self->setAttributables($preferences, $topics);
    }

    /**
     * @param NotificationPreferences $preferences
     * @param TopicCollection $topics
     * @return void
     */
    protected function setFixables(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if ($preferences->get(NotifyOn::FIXED)) {
            foreach ($topics as $topic) {
                if (is_a($topic, Fixable::class)) {
                    $fixable = new FixedTopic($topic);
                    $topics->add($fixable);
                }
            }
        }
    }

    /**
     * @param NotificationPreferences $preferences
     * @param TopicCollection $topics
     * @return void
     */
    protected function setLabelables(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if ($preferences->get(NotifyOn::LABELED)) {
            foreach ($topics as $topic) {
                if (is_a($topic, Labelable::class)) {
                    $labelable = new LabeledTopic($topic);
                    $topics->add($labelable);
                }
            }
        }
    }

    /**
     * @param NotificationPreferences $preferences
     * @param TopicCollection $topics
     * @return void
     */
    protected function setDeliverables(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if (!$preferences->get(NotifyOn::REDUNDANT)) {
            foreach ($topics as $topic) {
                $deliverable = new EmailSentTopic($topic);
                $topics->add($deliverable);
            }
        }
    }

    /**
     * @param NotificationPreferences $preferences
     * @param TopicCollection $topics
     * @return void
     */
    protected function setGroupFilterables(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if ($preferences->get(NotifyOn::GROUP_NIGHTLY)) {
            foreach ($topics as $topic) {
                $group = new GroupMembershipTopic($topic);
                $group->setGroup(BuildGroup::NIGHTLY);
                $topics->add($group);
            }
        }
    }

    /**
     * @param NotificationPreferences $preferences
     * @param TopicCollection $topics
     * @return void
     */
    protected function setAttributables(NotificationPreferences $preferences, TopicCollection $topics)
    {
        if ($preferences->get(NotifyOn::AUTHORED)) {
            foreach ($topics as $topic) {
                if (!is_a($topic, LabeledTopic::class)) {
                    $attributable = new AuthoredTopic($topic);
                    $topics->add($attributable);
                }
            }
        }
    }
}
