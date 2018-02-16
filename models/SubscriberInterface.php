<?php

use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Messaging\Topic\TopicCollection;

interface SubscriberInterface
{
    /**
     * SubscriberInterface constructor.
     * @param NotificationPreferences $preferences
     * @param TopicCollection|null $topics
     */
    public function __construct(
        NotificationPreferences $preferences,
        TopicCollection $topics = null
    );

    /**
     * @param ActionableBuildInterface $build
     * @return bool
     */
    public function hasBuildTopics(ActionableBuildInterface $build);

    /**
     * @return TopicCollection
     */
    public function getTopics();

    /**
     * @return string
     */
    public function getAddress();

    /**
     * @param $address
     * @return mixed
     */
    public function setAddress($address);

    /**
     * @return \CDash\Collection\LabelCollection
     */
    public function getLabels();
}
