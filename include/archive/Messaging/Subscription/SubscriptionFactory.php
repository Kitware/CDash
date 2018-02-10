<?php
namespace CDash\archive\archive\Messaging\Subscription;

use ActionableBuildInterface;
use CDash\Messaging\Collection\SubscriptionCollection;
use CDash\Messaging\Collection\TopicCollection;
use CDash\Messaging\Topic\BuildWarningTopic;
use CDash\Messaging\Topic\BuildErrorTopic;
use CDash\Messaging\Topic\ConfigureTopic;
use CDash\Messaging\Topic\DynamicAnalysisTopic;
use CDash\Messaging\Topic\TestFailureTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Messaging\Topic\UpdateTopic;
use Project;
use UserProject;

/**
 * Class SubscriptionFactory
 * @package CDash\Messaging\Subscription
 */
class SubscriptionFactory
{
    /**
     * @param ActionableBuildInterface $build
     * @return Subscription
     */
    public function createSubscription(ActionableBuildInterface $handler) : Subscription
    {
        $topic_collection = new TopicCollection();

        switch ($handler->getType()) {
            case ActionableBuildInterface::TYPE_UPDATE:
                $topic_collection->addTopic(new UpdateTopic());
                break;
            case ActionableBuildInterface::TYPE_CONFIGURE:
                $topic_collection->addTopic(new ConfigureTopic());
                break;
            case ActionableBuildInterface::TYPE_BUILD:
                $topic_collection
                    ->addTopic(new BuildWarningTopic())
                    ->addTopic(new BuildErrorTopic());
                break;
            case ActionableBuildInterface::TYPE_TEST:
                $topic_collection->addTopic(new TestFailureTopic());
                break;
            case ActionableBuildInterface::TYPE_DYNAMIC_ANALYSIS:
                $topic_collection->addTopic(new DynamicAnalysisTopic());
                break;
        }

        $builds = $handler->getActionableBuilds();
        $project = $builds[0]->GetProject();
        $user_topics = $project->GetProjectSubscriberTopics();

        foreach ($user_topics as $topic) {
            $topic_collection->addTopic($topic);
        }

        $subscription = new Subscription($handler);
        $subscription->addTopicCollection($topic_collection);
        return $subscription;
    }
}
