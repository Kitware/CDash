<?php
namespace CDash\Messaging\Subscription;

use ActionableBuildInterface;
use CDash\Collection\SubscriberCollection;
use CDash\Model\SubscriberInterface;

class UserSubscriptionBuilder implements SubscriptionBuilderInterface
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
     */
    public function __construct(ActionableBuildInterface $submission)
    {
        $this->submission = $submission;
    }

    /**
     * @param SubscriptionCollection $subscriptions
     * @return void
     */
    public function build(SubscriptionCollection $subscriptions)
    {
        $factory = $this->getSubscriptionFactory();

        $project = $this->submission->GetProject();
        $site = $this->submission->GetSite();
        $subscribers = $project->GetSubscriberCollection();

        Subscription::setMaxDisplayItems($project->EmailMaxItems);

        foreach ($subscribers as $subscriber) {
            /** @var SubscriberInterface $subscriber */
            if ($subscriber->hasBuildTopics($this->submission)) {
                $subscription = $factory->create();
                $subscription
                    ->setSubscriber($subscriber)
                    ->setProject($project)
                    ->setSite($site);
                $subscriptions->add($subscription);
            }
        }
    }

    /**
     * @return SubscriptionCollection
     */
    protected function getSubscriptionCollection()
    {
        if (is_null($this->subscriptions)) {
            $this->subscriptions = new SubscriptionCollection();
        }
        return $this->subscriptions;
    }

    /**
     * TODO: PHPDI exists now, refactor accordingly
     * @return SubscriptionFactory
     */
    protected function getSubscriptionFactory()
    {
        if (is_null($this->subscriptionFactory)) {
            $this->subscriptionFactory = new SubscriptionFactory();
        }
        return $this->subscriptionFactory;
    }
}
