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

namespace CDash\Model;

use ActionableBuildInterface;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Messaging\Topic\Topic;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Messaging\Topic\TopicDecorator;

/**
 * Class Subscriber
 *
 * A subscriber is a wrapper for a User and contains properties and methods that concern,
 * specifically, a user's preference regarding notifications and the content of those
 * notifications.
 */
class Subscriber implements SubscriberInterface
{
    /** @var NotificationPreferences */
    private $preferences;

    /** @var TopicCollection */
    private $topics;

    /** @var User */
    private $user;

    /**
     * Subscriber constructor.
     */
    public function __construct(
        NotificationPreferences $preferences,
        ?TopicCollection $topics = null,
        ?User $user = null,
    ) {
        $this->preferences = $preferences;
        $this->topics = $topics;
        $this->user = $user ? $user : new User();
    }

    /**
     * @return bool
     */
    public function hasBuildTopics(ActionableBuildInterface $submission)
    {
        $topics = $this->getTopics();
        $collection = $submission->GetTopicCollectionForSubscriber($this);
        if (count($collection) > 0) {
            $builds = $submission->GetBuildCollection();
            $buildGroup = $submission->GetBuildGroup();

            if ($buildGroup->GetSummaryEmail() == 1) {
                $this->preferences->set(NotifyOn::SUMMARY, true);
            }

            TopicDecorator::decorate($collection, $this->preferences);
            /** @var Topic $topic */
            foreach ($collection as $topic) {
                $topic->setSubscriber($this);
                foreach ($builds as $build) {
                    if ($topic->subscribesToBuild($build)) {
                        $topic->addBuild($build);
                        $topics->add($topic);
                    }
                }
            }
        }

        return $topics->count() > 0;
    }

    /**
     * @return TopicCollection
     */
    public function getTopics()
    {
        if (is_null($this->topics)) {
            $this->topics = new TopicCollection();
        }
        return $this->topics;
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return $this->user->Email;
    }

    /**
     * @return Subscriber
     */
    public function setAddress($address)
    {
        $this->user->Email = $address;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getLabels()
    {
        return $this->user->GetLabelCollection();
    }

    /**
     * @return Subscriber
     */
    public function setLabels(array $labels)
    {
        foreach ($labels as $label) {
            $this->user->AddLabel($label);
        }
        return $this;
    }

    /**
     * @return NotificationPreferences
     */
    public function getNotificationPreferences()
    {
        return $this->preferences;
    }

    public function setUserId($userId)
    {
        $this->user->Id = $userId;
        return $this;
    }

    public function getUserId()
    {
        return $this->user->Id;
    }

    public function getUserCredentials()
    {
        return $this->user->GetRepositoryCredentials();
    }
}
