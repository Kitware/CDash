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

        // special case handling here: if the decorated topic is a LabeledTopic
        // and the topic exists then we allow the labeled subscription to override
        // the authored topic. There was consideration to not allow AuthoredTopics
        // to decorate LabeledTopics, but doing so meant that for each AuthoredTopic,
        // you would also need a matching labeled topic, meaning that you would have
        // to do twice the amount of checking (looping) per build.
        if ($parentTopic && is_a($this->topic, LabeledTopic::class)) {
            return true;
        } else {
            return $parentTopic &&
                in_array($this->subscriber->getAddress(), $build->GetCommitAuthors());
        }
    }

    /**
     * @param Build $build
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        // TODO: q: do we need to do this again here?
        // a: Only if subscribesToBuild has not yet been called, but if it has been called
        //    how do we determine that the build passed in here is the same build that was
        //    verified in subscribesToBuild?
        return in_array($this->subscriber->getAddress(), $build->GetCommitAuthors());
    }
}
