<?php
namespace CDash\Messaging\Topic;

use Build;

class UpdateErrorTopic extends Topic
{

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        // TODO: Implement subscribesToBuild() method.
    }
}
