<?php
namespace CDash\Messaging\Topic;

use CDash\Collection\BuildErrorCollection;
use CDash\Collection\LabelCollection;
use CDash\Model\Build;
use CDash\Model\BuildError;

class BuildErrorTopic extends Topic implements Decoratable, Fixable, Labelable
{
    private $collection;
    private $type;
    private $diff;

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
        $this->diff = $build->GetDiffWithPreviousBuild();
        if ($this->diff) {
            $type = $this->getTopicName();
            $subscribe = $this->diff[$type]['new'] > 0;
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
     * // TODO: refactor itemHasTopicSubject, remove callables from Topic and subclasses & remove Build from signature
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        return $item->Type === $this->type;
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

    /**
     * @return bool
     */
    public function hasFixes()
    {
        $key = $this->getTopicName();
        return $this->diff && $this->diff[$key]['fixed'] > 0;
    }

    /**
     * @return array
     */
    public function getFixes()
    {
        $key = $this->getTopicName();
        if ($this->diff) {
            return $this->diff[$key];
        }
    }

    /**
     * @param Build $build
     * @return LabelCollection
     */
    public function getLabelsFromBuild(Build $build)
    {
        return $build->GetLabelCollection();
    }

    /**
     * @param Build $build
     * @param LabelCollection $labels
     * @return void
     */
    public function setTopicDataWithLabels(Build $build, LabelCollection $labels)
    {
        // We've already determined that the build has the subscribed labels
        // so here we can just use setTopicData
        $this->setTopicData($build);
    }
}
