<?php
namespace CDash\Messaging\Topic;

use Build;
use CDash\Collection\ArrayCollection;
use CDash\Collection\Collection;
use CDash\Collection\CollectionCollection;
use CDash\Collection\ConfigureCollection;
use CDash\Collection\LabelCollection;
use CDash\Collection\TestCollection;

class LabeledTopic extends Topic implements DecoratableInterface
{
    /** @var  ArrayCollection $labeledCollection */
    protected $topicCollection;

    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build)
    {
        $labels = $this->subscriber->getLabels();
        if (!count($labels)) {
            return false;
        }

        $buildLabels = $build->GetAggregatedLabels();
        foreach ($labels as $label) {
            if (in_array($label, $buildLabels)) {
                return true;
            }
        }
        return false;
    }

    public function setTopicData(Build $build)
    {
        switch ($build->GetActionableType()) {
            case \ActionableTypes::TEST:
                $this->setTestItems($build);
                break;
            case \ActionableTypes::CONFIGURE:
                $this->setConfigureItems($build);
                break;
        }
    }

    protected function setTestItems(Build $build)
    {
        $tests = $build->GetTestCollection();
        $labels = $this->subscriber->getLabels();
        $topics = $this->getTopicCollection();
        $collection = $topics->get(TestCollection::class);

        foreach ($labels as $label) {
            foreach ($tests as $test) {
                if ($test->isLabeled($label)) {
                    if (!$collection) {
                        $collection = new TestCollection();
                        $topics->addItem($collection, TestCollection::class);
                    }
                    $collection->addItem($test, $label);
                }
            }
        }
    }

    protected function setConfigureItems(Build $build)
    {
        $labels = $this->subscriber->getLabels();
        $topics = $this->getTopicCollection();
        $collection = $topics->get(ConfigureCollection::class);

        foreach ($labels as $label) {
            if ($build->isLabeled($label)) {
                if (!$collection) {
                    $collection = new ConfigureCollection();
                    $topics->add($collection, ConfigureCollection::class);
                }
                $collection->addItem($build->GetBuildConfigure(), $label);
            }
        }
    }

    protected function getItemCollection(Collection $collection)
    {
        $collectionClass = get_class($collection);
        $topics = $this->getTopicCollection();
        if (!$topics->has($collectionClass)) {
            $topics->add(new $collectionClass());
        }
        return $topics->get($collectionClass);
    }

    public function mergeTopics(TopicCollection $topics)
    {
        foreach ($topics as $topic) {
            $collection = $topic->getTopicCollection();
            $itemCollection = $this->getItemCollection($collection);
            if ($itemCollection) {
                foreach ($itemCollection as $item) {
                    $collection->add($item);
                }
                $this->topicCollection->remove(get_class($itemCollection));
            }
        }

        $collection = $this->getTopicCollection();

        if ($collection->hasItems()) {
            foreach ($this->getTopicCollection() as $collection) {
                switch (get_class($collection)) {
                    case TestCollection::class:
                        $topic = new TestFailureTopic();
                        break;
                    case ConfigureCollection::class:
                        $topic = new ConfigureTopic();
                        break;
                }

                if ($topic) {
                    foreach ($this->getBuildCollection() as $build) {
                        $topic->addBuild($build);
                    }
                    $topics->add($topic);
                }
            }
        }
    }

    public function getTopicCollection()
    {
        if (!$this->topicCollection) {
            $this->topicCollection = new CollectionCollection();
        }
        return $this->topicCollection;
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
