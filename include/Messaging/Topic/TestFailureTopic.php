<?php
namespace CDash\Messaging\Topic;

use Build;
use CDash\Collection\TestCollection;

class TestFailureTopic extends Topic implements DecoratableInterface
{
    private $collection;

    public function getTopicDescription()
    {
        return 'Tests Failing';
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
            $collection->add($test);
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
        if (!$this->labels) {
            $this->labels = [];
        }

        $subscribed_labels = $this->subscriber->getLabels();
        foreach ($build->GetTestCollection() as $test) {
            foreach ($test->GetLabelCollection() as $label) {
                if (in_array($label->Text, $subscribed_labels)) {
                    $this->labels[] = $label->Text;
                }
            }
        }
        return (bool)(count($this->labels));
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
}
