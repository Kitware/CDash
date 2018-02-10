<?php
namespace CDash\archive\Messaging\Topic;

use ActionableBuildInterface;
/**
 * Class BuildWarningTopic
 * @package CDash\Messaging\Topic
 */
class BuildWarningTopic extends AbstractTopic implements TopicDiscoveryInterface
{

    public function hasTopic(ActionableBuildInterface $handler = null): boolean
    {
        foreach ($handler->getActionableBuilds() as $label => $build) {
            if ($build->GetNumberOfWarnings() > 0) {
                return true;
            }
        }
        return false;
    }

    public function getTopicType(): string
    {
        return ActionableBuildInterface::TYPE_BUILD;
    }

    /**
     * @return int
     */
    public function getTopicMask(): int
    {
        return Topic::TOPIC_WARNING;
    }
}
