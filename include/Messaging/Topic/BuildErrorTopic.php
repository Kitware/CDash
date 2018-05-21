<?php
namespace CDash\Messaging\Topic;

use CDash\Collection\BuildErrorCollection;
use CDash\Model\Build;

class BuildErrorTopic extends Topic implements DecoratableInterface
{
    private $collection;
    private $type;

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $ancestorSubscribe = is_null($this->topic) ? true : $this->topic->subscribesToBuild($build);
        $subscribe = $ancestorSubscribe && $build->GetBuildErrorCount($this->type) > 0;
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
     * @param int $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    public function getTopicName()
    {
        // For now the use of == here is intentional
        // TODO: initialize type?
        if ($this->type == Build::TYPE_ERROR) {
            return 'BuildError';
        } else if ($this->type === Build::TYPE_WARN) {
            return 'BuildWarning';
        }
    }

    public function getTopicDescription()
    {
        if ($this->type == Build::TYPE_ERROR) {
            return 'Errors';
        } else if ($this->type === Build::TYPE_WARN) {
            return 'Warnings';
        }
    }
}
