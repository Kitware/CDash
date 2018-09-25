<?php
namespace CDash\Messaging\Topic;

use CDash\Collection\LabelCollection;
use CDash\Model\Build;
use CDash\Collection\TestCollection;
use CDash\Model\Label;
use CDash\Model\Test;

class TestFailureTopic extends Topic implements Decoratable, Fixable, Labelable
{
    protected $collection;
    protected $diff;

    /**
     * This method queries the build to check for failed tests
     *
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $subscribe = false;
        $this->diff = $build->GetDiffWithPreviousBuild();
        if ($this->diff) {
            $subscribe = $this->diff['TestFailure']['failed']['new'] > 0
                || $this->diff['TestFailure']['passed']['broken'] > 0
                || $this->diff['TestFailure']['notrun']['new'] > 0;
        }

        return $subscribe;
    }

    /**
     * This method sets a build's failed tests in a TestCollection
     *
     * @param Build $build
     * @return void
     */
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

    /**
     * This method returns the TestCollection containing a build's failed tests
     *
     * @return \CDash\Collection\CollectionInterface|TestCollection
     */
    public function getTopicCollection()
    {
        if (!$this->collection) {
            $this->collection = new TestCollection();
        }
        return $this->collection;
    }

    /**
     * This method returns the subject of the topic
     *
     * @return string
     *
     * TODO: is it possible to create a convention where this method can be abstracted to simply:
     *   return __CLASS__;
     */
    public function getTopicName()
    {
        return self::TEST_FAILURE;
    }

    /**
     * This method checks its subscriber collection for the presence of labels which the subscriber
     * is subscribed to. It then attempts to match those labels to the build's tests, returning
     * true upon a match and false otherwise.
     *
     * @param Build $build
     * @return bool
     */
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

    /**
     * This method will return the LabelCollection from a \CDash\Model\Test
     *
     * @param Test $subject
     * @return \CDash\Collection\LabelCollection
     */
    public function getSubjectLabelCollection(Test $subject)
    {
        return $subject->GetLabelCollection();
    }

    /**
     * @return string[]
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
     * This method will determine which of a Build's tests meet the criteria for adding to this
     * topic's TestCollection.
     *
     * @param Build $build
     * @param Test $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item)
    {
        if ($item->HasFailed()) {
            return true;
        }

        if ($item->HasNotRun()) {
            if ($item->Details !== Test::DISABLED) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string
     */
    public function getTopicDescription()
    {
        return 'Failing Tests';
    }

    /**
     * @return bool
     */
    public function hasFixes()
    {
        return $this->diff && $this->diff['TestFailure']['failed']['fixed'] > 0;
    }

    /**
     * @return array
     */
    public function getFixes()
    {
        $fixed = [];
        if ($this->diff) {
            $fixed = $this->diff['TestFailure'];
        }
        return $fixed;
    }

    /**
     * @param Build $build
     * @param LabelCollection $labels
     * @return void
     */
    public function setTopicDataWithLabels(Build $build, LabelCollection $labels)
    {
        $collection = $this->getTopicCollection();
        $tests = $build->GetTestCollection();
        /** @var Test $test */
        foreach ($tests as $test) {
            if ($this->itemHasTopicSubject($build, $test)) {
                foreach ($labels as $label) {
                    $testLabels = $test->GetLabelCollection();
                    if ($testLabels->has($label->Text)) {
                        $collection->add($test);
                    }
                }
            }
        }
    }

    /**
     * @param Build $build
     * @return LabelCollection
     */
    public function getLabelsFromBuild(Build $build)
    {
        $tests = $build->GetTestCollection();
        $collection = new LabelCollection();
        /** @var Test $test */
        foreach ($tests as $test) {
            // No need to bother with passed tests
            if ($test->HasFailed() || $test->HasNotRun()) {
                if ($test->HasNotRun() && isset($test->Details) && $test->Details === Test::DISABLED) {
                    continue;
                }
                /** @var Label $label */
                foreach($test->GetLabelCollection() as $label) {
                    $collection->add($label);
                }
            }
        }
        return $collection;
    }
}
