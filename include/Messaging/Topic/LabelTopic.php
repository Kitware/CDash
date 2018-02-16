<?php
namespace CDash\Messaging\Topic;

use Build;

class LabelTopic extends Topic
{
    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $subscribe = in_array($this->subscriber->getLabels(), $build->GetLabelNames());
        return $subscribe;
    }
}
