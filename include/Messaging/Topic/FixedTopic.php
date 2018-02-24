<?php
namespace CDash\Messaging\Topic;

use Build;

class FixedTopic extends Topic
{

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        // TODO: Implement subscribesToBuild() method.
    }

    /**
     * @param Build $build
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        // TODO: Implement itemHasTopicSubject() method.
    }
}
