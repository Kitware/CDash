<?php
namespace CDash\Messaging\Topic;

use Build;

class FixedTopic extends Topic implements AuthoredByInterface
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
