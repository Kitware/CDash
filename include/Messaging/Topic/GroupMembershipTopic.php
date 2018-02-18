<?php
namespace CDash\Messaging\Topic;

use Build;

class GroupMembershipTopic extends Topic
{
    /**
     * @var string $group
     */
    private $group;

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $parentTopic = is_null($this->topic) ? true : $this->topic->subscribesToBuild($build);
        $subscribe = $parentTopic && $this->group === $build->GetBuildType();
        return $subscribe;
    }

    public function setGroup(string $group)
    {
        $this->group = $group;
    }
}
