<?php
namespace CDash\Messaging\Subscription;

use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Project;

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
}
