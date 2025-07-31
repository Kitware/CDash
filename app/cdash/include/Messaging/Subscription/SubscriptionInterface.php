<?php

namespace CDash\Messaging\Subscription;

use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Project;
use CDash\Model\SubscriberInterface;

interface SubscriptionInterface
{
    /**
     * @return TopicCollection
     */
    public function getTopicCollection();

    /**
     * @return string
     */
    public function getBuildSummary();

    /**
     * @return string
     */
    public function getRecipient();

    /**
     * @return Project
     */
    public function getProject();

    public function getSubscriber(): SubscriberInterface;
}
