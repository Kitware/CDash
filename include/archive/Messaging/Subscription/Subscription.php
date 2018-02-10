<?php
namespace CDash\archive\archive\Messaging\Subscription;

use ActionableBuildInterface;
use CDash\Messaging\Collection\SubscriptionCollection;
use CDash\Messaging\Collection\TopicCollection;
use CDash\Messaging\Topic\TopicDiscoveryInterface;

/**
 * Class Subscription
 * @package CDash\Messaging\Subscription
 */
class Subscription
{
    /** @var SubscriptionCollection $Subscriptions */
    private $Subscriptions;

    /** @var  TopicCollection $Topics */
    private $Topics;

    /** @var  ActionableBuildInterface $Handler */
    private $Handler;

    public function __construct(ActionableBuildInterface $handler)
    {
        $this->Handler = $handler;
    }

    public function addSubscription(UserSubscription $user_subscription)
    {
        $this->Subscriptions->add($user_subscription);
        return $this;
    }

    public function addSubscriptionCollection(SubscriptionCollection $subscriptions)
    {
        $this->Subscriptions = $subscriptions;
    }

    public function addTopic(TopicDiscoveryInterface $topic) : Subscription
    {
        $this->Topics->add($topic);
        return $this;
    }

    public function addTopicCollection(TopicCollection $topics)
    {
        $this->Topics = $topics;
    }

    public function getTopicCollection() : TopicCollection
    {
        $this->Topics->rewind();
        return $this->Topics;
    }

    public function send(MessageBuilder $builder)
    {
        foreach ($this->Subscribers as $subscriber) {

        }
    }
}
