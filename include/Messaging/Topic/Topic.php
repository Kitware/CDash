<?php
namespace CDash\Messaging\Topic;

use Build;
use BuildGroup;
use CDash\Collection\BuildCollection;
use CDash\Collection\CollectionInterface;
use SubscriberInterface;

abstract class Topic implements TopicInterface
{
    const TEST_FAILURE = 'TestFailure';
    const LABELED = 'Labeled';

    /** @var  SubscriberInterface $subscriber */
    protected $subscriber;

    /** @var  mixed $topicData */
    protected $topicData;

    /** @var  Build $build */
    private $build;

    /** @var  BuildCollection */
    private $buildCollection;

    /** @var Topic $topic */
    protected $topic;

    /** @var  string[] $labels */
    protected $labels;

    /**
     * Topic constructor.
     * @param TopicInterface|null $topic
     */
    public function __construct(TopicInterface $topic = null)
    {
        $this->topic = $topic;
    }

    public function addBuild(Build $build)
    {
        $collection = $this->getBuildCollection();
        $collection->add($build);
        $this->setTopicData($build);
        return $this;
    }

    /**
     * @param Build $build
     * @return Topic
     */
    public function setBuild(Build $build)
    {
        $this->build = $build;
        return $this;
    }

    /**
     * @return Build
     */
    public function getBuild()
    {
        return $this->build;
    }

    /**
     * @param SubscriberInterface $subscriber
     * @return Topic
     */
    public function setSubscriber(SubscriberInterface $subscriber)
    {
        $this->subscriber = $subscriber;
        if ($this->topic) {
            $this->topic->setSubscriber($subscriber);
        }
        return $this;
    }

    /**
     * @param Build $build
     * @return $this
     */
    public function setTopicData(Build $build)
    {
        if ($this->topic) {
            $this->topic->setTopicData($build);
        }
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTopicData()
    {
        return $this->topicData;
    }

    /**
     * A method of convenience that returns of the class without the trailing 'Topic'. If a child
     * class does not conform to the conventional naming--i.e. the name of the Topic followed by
     * the word 'Topic'--then it should override this method.
     *
     * @return string
     */
    public function getTopicName()
    {
        $name = '';
        if ($this->topic) {
            $name = $this->topic->getTopicName();
        }
        return $name;
    }

    public function getTopicDescription()
    {
        return '';
    }

    /**
     * @return BuildCollection
     */
    protected function getBuildCollection()
    {
        if (!$this->buildCollection) {
            $this->buildCollection = new BuildCollection();
        }

        return $this->buildCollection;
    }

    /**
     * @return bool
     */
    public function hasSubscribedLabels()
    {
        if ($this->topic) {
            return $this->topic->hasSubscribedLabels();
        }
        return (bool)(count($this->labels));
    }
}
