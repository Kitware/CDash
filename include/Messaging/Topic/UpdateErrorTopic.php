<?php
namespace CDash\Messaging\Topic;

use CDash\Model\Build;

class UpdateErrorTopic extends Topic implements Decoratable
{
    use IssueTemplateTrait;

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $buildUpdate = $build->GetBuildUpdate();
        return $buildUpdate && $buildUpdate->Status > 0;
    }

    /**
     * @param Build $build
     * @return $this
     */
    public function addBuild(Build $build)
    {
        $collection = $this->getBuildCollection();
        $collection->add($build);
        return $this;
    }

    /**
     * @param Build $build
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        return true;
    }

    public function getTopicName()
    {
        return Topic::UPDATE_ERROR;
    }

    public function getTopicDescription()
    {
        return 'Update Errors';
    }
}
