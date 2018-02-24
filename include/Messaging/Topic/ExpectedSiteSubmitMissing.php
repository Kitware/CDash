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
