<?php
namespace CDash\Messaging\Topic;

use Build;

class AnyCheckinIssueTopic extends Topic implements CancelationInterface
{
    use CancelationTrait;

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        return true;
    }
}
