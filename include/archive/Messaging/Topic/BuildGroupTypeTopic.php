<?php
namespace CDash\archive\Messaging\Topic;

use ActionableBuildInterface;
use Topic\TopicDiscoveryInterface;

/**
 * Class BuildGroupTypeTopic
 * @package CDash\Messaging\Topic
 */
class BuildGroupTypeTopic extends AbstractTopic implements TopicDiscoveryInterface
{
    /**
     * @param ActionableBuildInterface|null $handler
     * @return bool
     */
    public function hasTopic(ActionableBuildInterface $handler = null): boolean
    {
        $buildGroup = new \BuildGroup();
        $buildGroup->SetId($handler->getBuildGroupId());
        $buildGroup->Fill();
        return $buildGroup->GetType() === $this->Filter->getValue();
    }
}
