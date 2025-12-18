<?php

namespace CDash\Messaging\Subscription;

use App\Http\Submission\Handlers\ActionableBuildInterface;
use CDash\Model\SubscriberInterface;

class UserSubscriptionBuilder implements SubscriptionBuilderInterface
{
    /** @var ActionableBuildInterface */
    private $submission;

    /** @var SubscriptionFactory */
    private $subscriptionFactory;

    /**
     * SubscriptionBuilder constructor.
     */
    public function __construct(ActionableBuildInterface $submission)
    {
        $this->submission = $submission;
    }

    public function build(SubscriptionCollection $subscriptions): void
    {
        $factory = $this->getSubscriptionFactory();

        $project = $this->submission->GetProject();
        $site = $this->submission->GetSite();
        $subscribers = $project->GetSubscriberCollection();
        $buildGroup = $this->submission->GetBuildGroup();

        Subscription::setMaxDisplayItems($project->EmailMaxItems);

        foreach ($subscribers as $subscriber) {
            /** @var SubscriberInterface $subscriber */
            if ($subscriber->hasBuildTopics($this->submission)) {
                $subscription = $factory->create();
                $subscription
                    ->setSubscriber($subscriber)
                    ->setProject($project)
                    ->setSite($site)
                    ->setBuildGroup($buildGroup);

                $subscriptions->add($subscription);
            }
        }
    }

    /**
     * TODO: PHPDI exists now, refactor accordingly
     */
    protected function getSubscriptionFactory(): SubscriptionFactory
    {
        if (null === $this->subscriptionFactory) {
            $this->subscriptionFactory = new SubscriptionFactory();
        }
        return $this->subscriptionFactory;
    }
}
