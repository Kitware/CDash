<?php
namespace CDash\Messaging\Topic;

use CDash\Collection\BuildErrorCollection;
use CDash\Model\Build;

class BuildErrorTopic extends Topic implements DecoratableInterface
{
    private $collection;
    private $type;

    /**
     * When a user subscribes to receive notices for build errors or build warnings (and
     * similarly build failures) this method will return true if the user has not already
     * been notified for those events or if the has been notified but there are new events
     * not included in the previous notification.
     *
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $subscribe = false;
        $diff = $build->GetDiffWithPreviousBuild();
        if ($diff) {
            $type = $this->getTopicName();
            $subscribe = $diff[$type]['new'] > 0;
        }
        return $subscribe;
    }

    /**
     * @param Build $build
     * @return Topic|void
     */
    public function setTopicData(Build $build)
    {
        $collection = $this->getTopicCollection();
        foreach ($build->Errors as $error) {
            if ($this->itemHasTopicSubject($build, $error)) {
                $collection->add($error);
            }
        }
    }

    /**
     * @return int
     */
    public function getTopicCount()
    {
        $collection = $this->getTopicCollection();
        return $collection->count();
    }

    /**
     * @param Build $build
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        $criteria = $this->getTopicCallables();
        $hasTopicSubject = $item->Type === $this->type;
        foreach ($criteria as $criterion) {
            $hasTopicSubject = $hasTopicSubject && $criterion($build, $item);
            if (!$hasTopicSubject) {
                break;
            }
        }
        return $hasTopicSubject;
    }

    /**
     * @return BuildErrorCollection|\CDash\Collection\CollectionInterface
     */
    public function getTopicCollection()
    {
        if (!$this->collection) {
            $this->collection = new BuildErrorCollection();
        }
        return $this->collection;
    }

    /**
     * @param $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    public function getTopicName()
    {
        return $this->type == Build::TYPE_ERROR ? Topic::BUILD_ERROR : Topic::BUILD_WARNING;
    }

    public function getTopicDescription()
    {
        return $this->type === Build::TYPE_ERROR ? 'Errors' : 'Warnings';
    }
}
