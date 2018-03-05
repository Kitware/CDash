<?php
namespace CDash\Messaging\Topic;

use Build;

class AuthoredTopic extends Topic
{
    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $parentTopic = is_null($this->topic) ? true : $this->topic->subscribesToBuild($build);
        return $parentTopic &&
            in_array($this->subscriber->getAddress(), $build->GetCommitAuthors());
    }

    /**
     * @param Build $build
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        // b808c5746ff9f69e3bfc78f01f5880bf13456ebe
        // TODO: q: do we need to do this again here?
        // a: Only if subscribesToBuild has not yet been called, but if it has been called
        //    how do we determine that the build passed in here is the same build that was
        //    verified in subscribesToBuild?
        return in_array($this->subscriber->getAddress(), $build->GetCommitAuthors());
    }
}
