<?php
namespace CDash\Messaging\Topic;

use Build;

class MyCheckinIssueTopic extends Topic implements CancelationInterface
{
    use CancelationTrait;

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $subscribe = in_array($this->subscriber->getAddress(), $build->GetCommitAuthors());
        $this->cancelSubscription =  !$subscribe;
        return $subscribe;
    }
}
