<?php
namespace CDash\Messaging\Topic;

use Build;

class ExpectedSiteSubmitMissing extends Topic
{
    /** @var int $priority */
    private $priority = 7;

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
    public function getPriority()
    {
        return $this->priority;
    }
}
