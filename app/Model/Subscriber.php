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

use CDash\Collection\LabelCollection;
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Messaging\Topic\Topic;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Messaging\Topic\TopicDecorator;
use ActionableBuildInterface;
use CDash\Messaging\Topic\TopicInterface;

class Subscriber implements SubscriberInterface
{
    /** @var  NotificationPreferences $preferences */
    private $preferences;

    /** @var TopicCollection $topics */
    private $topics;

    /** @var  string[] */
    private $labels;

    /** @var User $user */
    private $user;

    /**
     * Subscriber constructor.
     * @param NotificationPreferences $preferences
     * @param TopicCollection|null $topics
     * @param User|null $user
     */
    public function __construct(
        NotificationPreferences $preferences,
        TopicCollection $topics = null,
        User $user = null
    ) {
        $this->preferences = $preferences;
        $this->topics = $topics;
        $this->user = $user ? $user : new User();
    }

    /**
     * @param ActionableBuildInterface $submission
     * @return bool
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function hasBuildTopics(ActionableBuildInterface $submission)
    {
        // Initialize the topic collection
        $topics = $this->getTopics();
        $builds = $submission->GetBuildCollection();
        $collection = $submission->GetTopicCollectionForSubscriber($this);
        if ($collection->hasItems()) {
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
     * @param $address
     * @return Subscriber
     */
    public function setAddress($address)
    {
        $this->user->Email = $address;
        return $this;
    }

    /**
     * @return LabelCollection
     */
    public function getLabels()
    {
        return $this->user->GetLabelCollection();
    }

    /**
     * @param array $labels
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
