<?php
namespace CDash\archive\Messaging\Subscription;

use CDash\Messaging\Collection\TopicCollection;
use CDash\Messaging\Topic\TopicDiscoveryInterface;
use User;
use UserProject;
use UserTopic;

/**
 * Class Subscription
 */
class UserSubscription
{
    private $TopicCollection;
    private $User;
    private $UserProject;
    private $UserTopic;

    public function __construct(
        User $user,
        UserProject $user_project,
        UserTopic $user_topic,
        TopicCollection $topic_collection
    )
    {
        $this->TopicCollection = $topic_collection;
        $this->User = $user;
        $this->UserProject = $user_project;
        $this->UserTopic = $user_topic;
    }

    public function getTopicCollection() : TopicCollection
    {
        return $this->TopicCollection;
    }

    public function addTopic(TopicDiscoveryInterface $topic) : UserSubscription
    {
        $this->TopicCollection->add($topic);
        return $this;
    }

    public function getUser() : User
    {
        return $this->User;
    }

    public function getUserProject() : UserProject
    {
        return $this->UserProject;
    }

    public function getUserTopic() : UserTopic
    {
        return $this->UserTopic;
    }
}
