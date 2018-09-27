<?php
namespace CDash\Messaging\Topic;

use CDash\Model\Build;

class AuthoredTopic extends Topic
{
    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $subscribe = $this->topic->subscribesToBuild($build)
            && $build->AuthoredBy($this->subscriber);
        return $subscribe;
    }

    public function getTemplate()
    {
        return $this->topic->getTemplate();
    }
}
