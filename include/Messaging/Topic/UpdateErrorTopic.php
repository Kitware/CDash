<?php
namespace CDash\Messaging\Topic;

use CDash\Model\Build;

class UpdateErrorTopic extends Topic implements DecoratableInterface
{
    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $buildUpdate = $build->GetBuildUpdate();
        return $buildUpdate->Status > 0;
    }

    /**
     * @param Build $build
     * @return Topic|void
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
        // TODO: Implement itemHasTopicSubject() method.
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
