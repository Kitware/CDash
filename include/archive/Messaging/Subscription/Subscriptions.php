<?php
namespace CDash\archive\Messaging\Subscription;

use CDash\Messaging\Collection\RecipientCollection;
use CDash\Messaging\Collection\TopicCollection;
use CDash\Messaging\Topic\TopicDiscoveryInterface;

class Subscriptions
{
    /** @var  TopicCollection $Topics */
    private $Topics;
    /** @var  RecipientCollection $Recipients */
    private $Recipients;

    public function addTopic(TopicDiscoveryInterface $topic)
    {
        $this->Topics->add($topic);
    }

    public function addTopicCollection(TopicCollection $topics)
    {
        $this->Topics = $topics;
    }

    public function getSubscribers(RecipientCollection $recipients)
    {
        $topic_mask = 0;
        $subscribers = new RecipientCollection();

        /** @var TopicDiscoveryInterface $topic */
        foreach ($this->Topics as $topic) {
            $topic_mask = $topic_mask | $topic->getTopicMask();
        }

        foreach ($recipients as $user) {
            if ($user->GetSubscriptions() === $topic_mask) {
                $subscribers->add($user);
            }
        }

        $this->Recipients = $subscribers;
        return $this->Recipients;
    }
}
