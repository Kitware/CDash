<?php

use App\Models\Site;
use CDash\Collection\BuildCollection;
use CDash\Collection\SubscriptionBuilderCollection;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Model\BuildGroup;
use CDash\Model\Project;
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

    public function GetSubscriptionBuilderCollection(): SubscriptionBuilderCollection;

    public function GetBuildGroup(): BuildGroup;
}
