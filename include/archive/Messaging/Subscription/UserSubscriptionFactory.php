<?php
namespace CDash\archive\Messaging\Subscription;

use CDash\Messaging\Collection\TopicCollection;
use User;
use UserProject;
use UserTopic;

class UserSubscriptionFactory
{
    private $User;
    private $UserProject;
    private $UserTopic;
    private $TopicCollection;

    public function __construct(
        User $user = null,
        UserProject $user_project = null,
        UserTopic $user_topic = null,
        TopicCollection $topic_collection = null
    )
    {
        $this->User = $user;
        $this->UserProject = $user_project;
        $this->UserTopic = $user_topic;
        $this->TopicCollection = $topic_collection;
    }

    public function createUserSubscription()
    {
        return new UserSubscription(
            $this->getUser(),
            $this->getUserProject(),
            $this->getUserTopic(),
            $this->getTopicCollection()
        );
    }

    /**
     * @return User
     */
    private function getUser() : User
    {
        if (!$this->User) {
            return new User();
        }
        return $this->User;
    }

    /**
     * @return UserProject
     */
    private function getUserProject() : UserProject
    {
        if (!$this->UserProject) {
            return new UserProject();
        }
        return $this->UserProject;
    }

    /**
     * @return UserTopic
     */
    private function getUserTopic() : UserTopic
    {
        if (!$this->UserTopic) {
            return new UserTopic();
        }
        return $this->UserTopic;
    }

    private function getTopicCollection() : TopicCollection
    {
        if (!$this->TopicCollection) {
            return new TopicCollection();
        }
        return $this->TopicCollection;
    }
}
