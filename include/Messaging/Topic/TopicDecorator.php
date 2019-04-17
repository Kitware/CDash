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
 * Class TopicDecorator
 * @package CDash\Messaging\Topic
 *
 * Given a TopicCollection and a subscriber's NotificationPreferences the TopicDecorator
 * "decorates" a submission's topics with more granular topics as indicated by user
 * preference.
 *
 * Topics come in two flavors--regular topics and decorate-able topics. What follows is a
 * discussion of the distinction.
 *
 * Decorate-able topics can be thought of as fundamental topics, e.g. DynamicAnalysisTopic,
 * BuildErrorTopic, or TestFailureTopic and they represent the type of build (that is, a phase
 * in the build process such as the dynamic analysis, the actual build itself, or testing)
 * represented by the xml submitted to CDash by CTest. Take the example where a user wishes to receive a notification
 * about a test failure but ignore test warnings. The handler that processed the submitted xml file (in this case, the
 * TestingHandler), will have already set a TestWarning topic and a TestFailure topic in the
 * TopicCollection.
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
     * The Fixable Interface represents classes that have the possibility of having been "fixed".
     * For instance, a failing test can be fixed and CDash is able to make the distinction of tests
     * that were previously failing, but are now fixed. Users may wish to be notified of this event
     * and may do so by setting their notification preferences to notify when things get fixed.
     *
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
     * The Labelable Interface represents a topic that has the possibility of having a label
     * attached to it. Users may wish to be notified about submissions with a particular label
     * and receive notification of these labeled items by indicating such in their preferences.
     *
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
     * The notion of deliverable in this case represents something that can be delivered, e.g.
     * a notification of any kind. Specifically this notion exists so that CDash discern if a
     * notification event has already taken place.
     *
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

        if ($preferences->get(NotifyOn::SUMMARY)) {
            foreach ($topics as $topic) {
                $summary = new BuildGroupSummaryTopic($topic);
                $topics->add($summary);
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
