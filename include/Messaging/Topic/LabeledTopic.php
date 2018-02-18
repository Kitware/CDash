<?php
namespace CDash\Messaging\Topic;

use Build;
use CDash\Collection\TestCollection;

class LabeledTopic extends Topic
{
    /** @var  TestCollection $testCollection */
    private $testCollection;

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

    /**
     * @param Build $build
     * @return bool
     */
    protected function checkBuildLabels(Build $build)
    {
        $preferences = $this->subscriber->getNotificationPreferences();
        $checkBuilds = $preferences->notifyOn('BuildWarning')
            || $preferences->notifyOn('BuildError');
        $hasLabels = (bool)count(array_intersect($this->labels, $build->GetLabelNames()));
        return $checkBuilds && $hasLabels;
    }

    /**
     * @param Build $build
     * @return bool
     */
    protected function checkTestLabels(Build $build)
    {
        $preferences = $this->subscriber->getNotificationPreferences();
        $collection = false;
        if ($preferences->notifyOn('TestFailure')) {
            foreach ($build->GetTestCollection() as $test) {
                foreach ($test->GetLabelCollection() as $label) {
                    if (in_array($label->Text, $this->labels)) {
                        $collection = $this->getTestCollection();
                        if (!$collection->has($test->Name)) {
                            $collection->add($test);
                        }
                        break;
                    }
                }
            }
        }
        return $collection && $collection->hasItems();
    }

    protected function getTestCollection()
    {
        if (!$this->testCollection) {
            $this->testCollection = new TestCollection();
        }
        return $this->testCollection;
    }

    public function getTopicName()
    {
        return self::LABELED;
    }
}
