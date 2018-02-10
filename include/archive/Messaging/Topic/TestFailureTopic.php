<?php
namespace CDash\archive\archive\Messaging\Topic;

use ActionableBuildInterface;

class TestFailureTopic extends AbstractTopic implements TopicDiscoveryInterface
{
    public function hasTopic(ActionableBuildInterface $build = null) : bool
    {
        foreach ($build->getActionableBuilds() as $label => $build) {
            if ($build->GetNumberOfFailedTests() > 0) {
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
        return Topic::TOPIC_TEST;
    }
}
