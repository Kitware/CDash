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

use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Model\BuildGroup;
use CDash\Singleton;

/**
 * Class TopicDecorator
 */
class TopicDecorator extends Singleton
{
    public static function decorate(TopicCollection $topics, NotificationPreferences $preferences): void
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
     */
    protected function setFixables(NotificationPreferences $preferences, TopicCollection $topics): void
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
     */
    protected function setLabelables(NotificationPreferences $preferences, TopicCollection $topics): void
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
     */
    protected function setDeliverables(NotificationPreferences $preferences, TopicCollection $topics): void
    {
        if (!$preferences->get(NotifyOn::REDUNDANT)) {
            foreach ($topics as $topic) {
                $deliverable = new EmailSentTopic($topic);
                $topics->add($deliverable);
            }
        }
    }

    protected function setGroupFilterables(NotificationPreferences $preferences, TopicCollection $topics): void
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

    protected function setAttributables(NotificationPreferences $preferences, TopicCollection $topics): void
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
