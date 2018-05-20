<?php
namespace CDash\Messaging\Topic;

use CDash\Model\Build;

class FixedTopic extends Topic
{
    private $diff;
    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $ancestorSubscribe = is_null($this->topic) ? true : $this->topic->subscribesToBuild($build);
        $subscribe = false;
        $diff = $build->GetErrorDifferences();
        if ($diff['buildwarningsnegative']  > 0
            || $diff['testfailednegative']  > 0
            || $diff['testnotrunnegative']  > 0
            || $diff['builderrorsnegative'] > 0
            || $diff['configurewarnings']   < 0
            || $diff['configureerrors']     < 0
        ) {
            $this->diff = $diff;
            $subscribe = true;
        }
        return $ancestorSubscribe && $subscribe;
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
