<?php
namespace CDash\Messaging\Topic;

use Build;
use BuildGroup;

class CheckinIssueNightlyOnlyTopic extends Topic implements CancelationInterface
{
    use CancelationTrait;

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $build_group = $build->GetBuildGroup();
        $subscribe = $build_group->GetName() === BuildGroup::TYPE_NIGHTLY;
        $this->cancelSubscription = !$subscribe;
        return $subscribe;
    }
}
