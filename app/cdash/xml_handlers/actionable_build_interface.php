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
    /**
     * @return Build[]
     * @deprecated Use GetBuildCollection() 02/04/18
     */
    public function getActionableBuilds();

    /**
     * @return BuildCollection
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function GetBuildCollection();

    /**
     * @return Project
     */
    public function GetProject();

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
