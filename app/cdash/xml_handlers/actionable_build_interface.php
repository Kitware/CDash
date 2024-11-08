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

    public function GetSite(): Site;

    public function GetTopicCollectionForSubscriber(SubscriberInterface $subscriber): TopicCollection;

    /**
     * @return Collection
     */
    public function GetSubscriptionBuilderCollection();

    /**
     * @return BuildGroup
     */
    public function GetBuildGroup();
}
