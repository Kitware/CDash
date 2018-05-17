<?php
namespace CDash\Messaging\Topic;

use CDash\Model\Build;
use CDash\Collection\CollectionCollection;
use CDash\Collection\TestCollection;

class TestFailureTopic extends Topic implements DecoratableInterface
{
    private $collection;

    public function getTopicDescription()
    {
        return 'Failing Tests';
    }

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $parentTopic = is_null($this->topic) ? true : $this->topic->subscribesToBuild($build);
        $subscribe = $parentTopic && $build->GetTestFailedCount() > 0;

        return $subscribe;
    }

    public function setTopicData(Build $build)
    {
        $collection = $this->getTopicCollection();
        $tests = $build->GetTestCollection();
        foreach ($tests as $test) {
            if ($this->itemHasTopicSubject($build, $test)) {
                $collection->add($test);
            }
        }
    }

    public function getTopicCollection()
    {
        if (!$this->collection) {
            $this->collection = new TestCollection();
        }
        return $this->collection;
    }

    public function getTopicName()
    {
        return self::TEST_FAILURE;
    }

    public function hasLabels(Build $build)
    {
        $subscribed_labels = $this->subscriber->getLabels();
        foreach ($build->GetTestCollection() as $test) {
            foreach ($test->GetLabelCollection() as $label) {
                if (in_array($label->Text, $subscribed_labels)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getSubjectLabelCollection(\Test $subject)
    {
        return $subject->GetLabelCollection();
    }

    /**
     * @return \string[]
     */
    public function getLabels()
    {
        if (!$this->labels) {
            $this->labels = [];
        }
        return $this->labels;
    }

    /**
     * @return int
     */
    public function getTopicCount()
    {
        return $this->getTopicCollection()->count();
    }

    /**
     * @param Build $build
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        /** @var \Test $item */
        $criteria = $this->getTopicCallables();
        $hasTopicSubject = $item->HasFailed();
        foreach ($criteria as $criterion) {
            $hasTopicSubject = $hasTopicSubject && $criterion($build, $item);
            if (!$hasTopicSubject) {
                break;
            }
        }
        return $hasTopicSubject;
    }
}
