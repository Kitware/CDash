<?php
namespace CDash\archive\Messaging\Topic;

use ActionableBuildInterface;

/**
 * Class BuildFailureTopic
 * @package CDash\Messaging\Topic
 */
class BuildErrorTopic extends AbstractTopic implements TopicDiscoveryInterface
{
    /**
     * @param ActionableBuildInterface|null $handler
     * @return bool
     */
    public function hasTopic(ActionableBuildInterface $handler = null): boolean
    {
        foreach ($handler->getActionableBuilds() as $build) {
            if ($build->GetNumberOfErrors() > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return int
     */
    public function getTopicMask(): int
    {
       return Topic::TOPIC_ERROR;
    }
}
