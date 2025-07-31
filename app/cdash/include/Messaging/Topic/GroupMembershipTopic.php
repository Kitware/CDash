<?php

namespace CDash\Messaging\Topic;

use CDash\Model\Build;

class GroupMembershipTopic extends Topic
{
    /**
     * @var string
     */
    private $group;

    public function subscribesToBuild(Build $build): bool
    {
        $parentTopic = is_null($this->topic) ? true : $this->topic->subscribesToBuild($build);
        $subscribe = $parentTopic && $this->group === $build->GetBuildType();
        return $subscribe;
    }

    /**
     * @param string $group
     */
    public function setGroup($group): void
    {
        $this->group = $group;
    }

    public function getTopicCount(): int
    {
        if ($this->topic) {
            return $this->topic->getTopicCount();
        }
        return 0;
    }

    public function itemHasTopicSubject(Build $build, $item): bool
    {
        // TODO: q: do we need to do this again here?
        // a: Only if subscribesToBuild has not yet been called, but if it has been called
        //    how do we determine that the build passed in here is the same build that was
        //    verified in subscribesToBuild?
        return $this->group === $build->GetBuildType();
    }
}
