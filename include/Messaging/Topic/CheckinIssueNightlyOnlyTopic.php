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
        $subscribe = $build->GetBuildType() === BuildGroup::NIGHTLY;
        $this->cancelSubscription = !$subscribe;
        return $subscribe;
    }
}
