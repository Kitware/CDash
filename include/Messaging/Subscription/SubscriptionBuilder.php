<?php
namespace CDash\Messaging\Subscription;

use ActionableBuildInterface;
use CDash\Collection\SubscriberCollection;
use Project;

class SubscriptionBuilder
{
    /** @var  \ActionableBuildInterface $handler */
    private $build;

    /** @var  \Project $project */
    private $project;

    /** @var SubscriptionFactory $subscriptionFactory */
    private $subscriptionFactory;

    /** @var  SubscriptionCollection $subscriptions */
    private $subscriptions;

    /** @var SubscriberCollection $subscribers */
    private $subscribers;

    /**
     * SubscriptionBuilder constructor.
     * @param ActionableBuildInterface $build
     * @param Project $project
     * @param SubscriptionCollection|null $subscriptions
     * @param SubscriberCollection|null $subscribers
     * @param SubscriptionFactory|null $subscriptionFactory
     */
    public function __construct(
        ActionableBuildInterface $build,
        Project $project,
        SubscriptionCollection $subscriptions = null,
        SubscriberCollection $subscribers = null,
        SubscriptionFactory $subscriptionFactory = null
    ) {
        $this->build = $build;
        $this->project = $project;
        $this->subscriptions = $subscriptions;
        $this->subscribers = $subscribers;
        $this->subscriptionFactory = $subscriptionFactory;
    }

    /**
     * @return SubscriptionCollection|null
     */
    public function build()
    {
        $subscriptions = $this->getSubscriptions();
        $subscribers = $this->getSubscribers();
        $factory = $this->getSubscriptionFactory();

        $this->project->GetProjectSubscribers($subscribers);

        Subscription::setMaxDisplayItems($this->project->EmailMaxItems);

        foreach ($subscribers as $subscriber) {
            /** @var \SubscriberInterface $subscriber */
            if ($subscriber->hasBuildTopics($this->build)) {
                $subscription = $factory->create();
                $subscription
                    ->setSubscriber($subscriber)
                    ->setTopicCollection($subscriber->getTopics())
                    ->setProject($this->project);

                $subscriptions->add($subscription);
            }
        }
        return $subscriptions;
    }

    protected function getSubscriptions()
    {
        if (is_null($this->subscriptions)) {
            $this->subscriptions = new SubscriptionCollection();
        }
        return $this->subscriptions;
    }

    protected function getSubscribers()
    {
        if (is_null($this->subscribers)) {
            $this->subscribers = new SubscriberCollection();
        }
        return $this->subscribers;
    }

    protected function getSubscriptionFactory()
    {
        if (is_null($this->subscriptionFactory)) {
            $this->subscriptionFactory = new SubscriptionFactory();
        }
        return $this->subscriptionFactory;
    }
}
