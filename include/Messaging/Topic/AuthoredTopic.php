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
            && $build->HasAuthor($this->subscriber->getAddress());
        return $subscribe;
    }

    /**
     * @param Build $build
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        return $this->topic->itemHasTopicSubject($build, $item);
    }
}
