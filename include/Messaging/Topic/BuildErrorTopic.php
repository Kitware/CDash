<?php
namespace CDash\Messaging\Topic;

use Build;

class BuildErrorTopic extends Topic implements DecoratableInterface
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
     * @return int
     */
    public function getTopicCount()
    {
        // TODO: Implement getTopicCount() method.
    }
}
