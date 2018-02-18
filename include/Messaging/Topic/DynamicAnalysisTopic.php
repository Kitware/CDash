<?php
namespace CDash\Messaging\Topic;

use Build;

class DynamicAnalysisTopic extends Topic implements DecoratableInterface
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
