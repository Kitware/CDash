<?php
namespace CDash\Messaging\Topic;

use CDash\Collection\BuildCollection;
use CDash\Collection\BuildErrorCollection;
use CDash\Model\ActionableTypes;
use CDash\Model\Build;
use CDash\Collection\Collection;
use CDash\Collection\CollectionCollection;
use CDash\Collection\ConfigureCollection;
use CDash\Collection\LabelCollection;
use CDash\Collection\TestCollection;

class LabeledTopic extends Topic implements DecoratableInterface
{
    /** @var  CollectionCollection $labeledCollection */
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

    /**
     * @param Build $build
     * @return Topic|void
     */
    public function setTopicData(Build $build)
    {
        switch ($build->GetActionableType()) {
            case ActionableTypes::TEST:
                $this->setTestItems($build);
                break;
            case ActionableTypes::CONFIGURE:
                $this->setConfigureItems($build);
                break;
            case ActionableTypes::BUILD_ERROR:
                $this->setBuildItems($build);
                break;
        }
    }

    protected function setBuildItems(Build $build)
    {
        $labels = $this->subscriber->getLabels();
        $topics = $this->getTopicCollection();
        $collection = $topics->get(BuildCollection::class);

        foreach ($labels as $label) {
            foreach ($build->Errors as $error) {
                if (isset($error->Labels)) {
                    $error_labels = array_map(function ($lbl) {
                        return $lbl->Text;
                    }, $error->Labels);
                    if (in_array($label, $error_labels)) {
                        if (!$collection) {
                            $collection = new BuildErrorCollection();
                            $topics->addItem($collection, BuildErrorCollection::class);
                        }
                        $collection->addItem($error, $label);
                    }
                }
            }
        }
    }

    /**
     * @param Build $build
     */
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

    /**
     * @param Build $build
     */
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

    /**
     * @param Collection $collection
     * @return mixed
     */
    protected function getItemCollection(Collection $collection)
    {
        $collectionClass = get_class($collection);
        $topics = $this->getTopicCollection();
        if (!$topics->has($collectionClass)) {
            $topics->add(new $collectionClass());
        }
        return $topics->get($collectionClass);
    }

    /**
     * When a LabeledTopic's collection is removed from the subscription, its contents must be
     * merged back into the subscriber's topic collection avoiding duplication of topics.
     *
     * @param TopicCollection $topics
     */
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
                $topic = null;
                // TODO: add default case to throw exception
                switch (get_class($collection)) {
                    case TestCollection::class:
                        $topic = new TestFailureTopic();
                        break;
                    case ConfigureCollection::class:
                        $topic = new ConfigureTopic();
                        break;
                    case BuildErrorCollection::class:
                        $topic = new BuildErrorTopic();
                        // Here we must make sure that we set the type (error or warning)
                        /** @var \CDash\Model\BuildFailure|\CDash\Model\BuildError $first_item*/
                        $first_item = $collection->current();
                        if (isset($first_item->Type)) {
                            $topic->setType($first_item->Type);
                        }
                        break;
                }

                if ($topic) {
                    $topic->setSubscriber($this->subscriber);
                    foreach ($this->getBuildCollection() as $build) {
                        $topic->addBuild($build);
                    }

                    $topics->add($topic);
                }
            }
        }
    }

    /**
     * @return CollectionCollection|\CDash\Collection\CollectionInterface
     */
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
