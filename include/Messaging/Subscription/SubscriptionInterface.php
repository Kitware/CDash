<?php
namespace CDash\Messaging\Subscription;

use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Project;
use CDash\Model\Subscriber;

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

    /**
     * @return Subscriber
     */
    public function getSubscriber();
}
