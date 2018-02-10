<?php
namespace CDash\archive\Messaging\Topic;

use ActionableBuildInterface;

class DynamicAnalysisTopic extends AbstractTopic implements TopicDiscoveryInterface
{

    /**
     * @param ActionableBuildInterface|null $handler
     * @return bool
     */
    public function hasTopic(ActionableBuildInterface $handler = null): boolean
    {
        // TODO: Implement hasTopic() method.
    }

    /**
     * @return int
     */
    public function getTopicMask(): int
    {
        // TODO: Implement getTopicMask() method.
    }
}
