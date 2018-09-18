<?php
namespace CDash\Messaging\Topic;

use CDash\Model\Build;

/**
 * Class FixedTopic
 * @package CDash\Messaging\Topic
 */
class FixedTopic extends Topic
{
    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $diff = $build->GetDiffWithPreviousBuild();
        $subscribe = (bool) $diff['BuildError']['fixed'] > 0
                         || $diff['BuildWarning']['fixed'] > 0
                         || $diff['TestFailure']['failed']['fixed'] > 0
                         || $diff['TestFailure']['notrun']['fixed'] > 0;
        return $subscribe;
    }

    /**
     * @param Build $build
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        // currently a noop because I think the diff property will suffice
    }
}
