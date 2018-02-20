<?php
namespace CDash\Messaging\Subscription;

use ActionableBuildInterface;
use CDash\Collection\SubscriberCollection;

class SubscriptionBuilder
{
    /** @var  \ActionableBuildInterface $submission */
    private $submission;

    /** @var SubscriptionFactory $subscriptionFactory */
    private $subscriptionFactory;

    /** @var  SubscriptionCollection $subscriptions */
    private $subscriptions;

    /**
     * SubscriptionBuilder constructor.
     * @param ActionableBuildInterface $submission
     * @param SubscriptionCollection|null $subscriptions
     * @param SubscriptionFactory|null $subscriptionFactory
     */
    public function __construct(
        ActionableBuildInterface $submission,
        SubscriptionCollection $subscriptions = null,
        SubscriberCollection $subscribers = null,
        SubscriptionFactory $subscriptionFactory = null
    ) {
        $this->submission = $submission;
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
        $factory = $this->getSubscriptionFactory();

        $project = $this->submission->GetProject();
        $site = $this->submission->GetSite();
        $subscribers = $project->GetSubscriberCollection();

        Subscription::setMaxDisplayItems($project->EmailMaxItems);

        foreach ($subscribers as $subscriber) {
            /** @var \SubscriberInterface $subscriber */
            if ($subscriber->hasBuildTopics($this->submission)) {
                $subscription = $factory->create();
                $subscription
                    ->setSubscriber($subscriber)
                    ->setProject($project)
                    ->setSite($site);
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

    protected function getSubscriptionFactory()
    {
        if (is_null($this->subscriptionFactory)) {
            $this->subscriptionFactory = new SubscriptionFactory();
        }
        return $this->subscriptionFactory;
    }
}
