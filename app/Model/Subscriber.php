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

use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Messaging\Topic\Topic;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Messaging\Topic\TopicFactory;
use ActionableBuildInterface;

class Subscriber implements SubscriberInterface
{
    /** @var  NotificationPreferences $preferences */
    private $preferences;

    /** @var  string $address */
    private $address;

    /** @var TopicCollection $topics */
    private $topics;

    /** @var  string[] */
    private $labels;

    private $userId;

    /**
     * SubscriberInterface constructor.
     * @param NotificationPreferences $preferences
     * @param TopicCollection|null $topics
     */
    public function __construct(
        NotificationPreferences $preferences,
        TopicCollection $topics = null
    ) {
        $this->preferences = $preferences;
        $this->topics = $topics;
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
        $user_topics = TopicFactory::createFrom($this->preferences);

        /** @var Topic $topic */
        foreach ($user_topics as $topic) {
            $topic->setSubscriber($this);
            foreach ($builds as $build) {
                if ($topic->subscribesToBuild($build)) {
                    $topic->addBuild($build);
                    // Check to ensure that the topic does not already exist
                    // TODO: refactor to check this first to avoid having to run subscribesToBuild
                    if (!$topics->has($topic->getTopicName())) {
                        $topics->add($topic);
                    }
                }
            }
        }
        return $topics->count() > 0;
    }

    protected function initializeTopics()
    {
        $topics = $this->getTopics();
        foreach (TopicFactory::createFrom($this->preferences) as $topic) {
            $topics->add($topic);
        }
        return (bool) count($topics);
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
        return $this->address;
    }

    /**
     * @param $address
     * @return Subscriber
     */
    public function setAddress($address)
    {
        $this->address = $address;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * @param array $labels
     * @return Subscriber
     */
    public function setLabels(array $labels)
    {
        $this->labels = $labels;
        return $this;
    }

    /**
     * @return \CDash\Messaging\Preferences\NotificationPreferencesInterface
     */
    public function getNotificationPreferences()
    {
        return $this->preferences;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
        return $this;
    }

    public function getUserId()
    {
        return $this->userId;
    }
}
