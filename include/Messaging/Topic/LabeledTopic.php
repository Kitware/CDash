<?php
namespace CDash\Messaging\Topic;

use Build;
use CDash\Collection\CollectionCollection;
use CDash\Collection\LabelCollection;
use CDash\Collection\TestCollection;

class LabeledTopic extends Topic
{
    /** @var  TestCollection $testCollection */
    private $testCollection;

    /** @var  CollectionCollection $labeledCollection */
    private $labeledCollection;

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $subscribe = false;
        if (!count($this->subscriber->getLabels())) {
            return $subscribe;
        }

        if ($this->topic->subscribesToBuild($build)) {
            $subscribe = $this->topic->hasLabels($build);
        }

        return $subscribe;
    }

    public function getLabeledCollection()
    {
        if (!$this->labeledCollection) {
            $this->labeledCollection = new CollectionCollection();
        }
        return $this->labeledCollection;
    }

    public function setTopicData(Build $build)
    {
        if ($this->topic) {
            $this->topic->setTopicData($build);
        }

        // now remove topics that do not
    }

    /**
     * @return TestCollection
     */
    protected function getTestCollection()
    {
        if (!$this->testCollection) {
            $this->testCollection = new TestCollection();
        }
        return $this->testCollection;
    }

    /**
     * @return string
     */
    public function getTopicName()
    {
        return self::LABELED;
    }

    /**
     * @return int
     */
    public function getTopicCount()
    {
        if ($this->topic) {
            return $this->topic->getTopicCount();
        }
        return 0;
    }

    /**
     * @param Build $build
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        /** @var LabelCollection $labels */
        $labels = $this->topic->getSubjectLabelCollection($item);
        $subscribed = $this->subscriber->getLabels();
        foreach ($subscribed as $label) {
            if ($labels->has($label)) {
                return true;
            }
        }
        return false;
    }
}
