<?php

use CDash\Collection\BuildCollection;
use CDash\Collection\Collection;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\Project;
use App\Models\Site;
use CDash\Model\SubscriberInterface;

/**
 * ActionableHandler
 */
interface ActionableBuildInterface
{
    public function GetBuildCollection(): BuildCollection;

    public function GetProject(): Project;

    /**
     * @return Site
     */
    public function GetSite();

    /**
     * @param SubscriberInterface $subscriber
     * @return TopicCollection
     */
    public function GetTopicCollectionForSubscriber(SubscriberInterface $subscriber);

    /**
     * @return Collection
     */
    public function GetSubscriptionBuilderCollection();

    /**
     * @return BuildGroup
     */
    public function GetBuildGroup();
}
