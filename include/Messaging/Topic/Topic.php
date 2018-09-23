<?php
namespace CDash\Messaging\Topic;

use CDash\Model\ActionableTypes;
use CDash\Model\Build;
use CDash\Collection\BuildCollection;
use CDash\Collection\CallableCollection;
use CDash\Collection\CollectionInterface;
use CDash\Model\SubscriberInterface;

abstract class Topic implements TopicInterface
{
    const BUILD_ERROR = 'BuildError';
    const BUILD_WARNING = 'BuildWarning';
    const CONFIGURE = 'Configure';
    const DYNAMIC_ANALYSIS = 'DynamicAnalysis';
    const LABELED = 'Labeled';
    const TEST_FAILURE = 'TestFailure';
    const UPDATE = 'Update';
    const UPDATE_ERROR = 'UpdateError';

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

    /** @var  CallableCollection $topicCallables */
    protected $topicCallables;

    /**
     * Topic constructor.
     * @param TopicInterface|Fixable|Labelable|null $topic
     */
    public function __construct(TopicInterface $topic = null)
    {
        if ($topic) {
            $callables = $topic->getTopicCallables();
            $callables->add([$this, 'itemHasTopicSubject']);
        }
        $this->topic = $topic;
    }

    /**
     * @param Build $build
     * @return $this
     */
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
     * @return CollectionInterface
     */
    public function getTopicCollection()
    {
        if ($this->topic) {
            return $this->topic->getTopicCollection();
        }
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
        if ($this->topic) {
            return $this->topic->getTopicDescription();
        }
        return '';
    }

    /**
     * @return BuildCollection
     */
    public function getBuildCollection()
    {
        if (!$this->buildCollection) {
            $this->buildCollection = new BuildCollection();
        }

        return $this->buildCollection;
    }

    public function getLabels()
    {
        if ($this->topic) {
            return $this->topic->getLabels();
        }
        return [];
    }

    public function getTopicCount()
    {
        if ($this->topic) {
            return $this->topic->getTopicCount();
        }
        return 0;
    }

    public function getTopicCallables()
    {
        if (!$this->topicCallables) {
            $this->topicCallables = new CallableCollection();
        }
        return $this->topicCallables;
    }

    public function hasSubscriberAlreadyBeenNotified(Build $build)
    {
        $category = ActionableTypes::$categories[$this->getTopicName()];
        $emailCollection = $build->GetBuildEmailCollection($category);
        $address = $this->subscriber->getAddress();

        return $emailCollection->has($address);
    }

    public function getFixed()
    {
        $fixes = [];
        if ($this->topic) {
            $fixes = $this->topic->getFixed();
        }
        return $fixes;
    }

    public function getTemplate()
    {
        return 'issue';
    }

    public function isA($class)
    {
        $it = is_a($this, $class);
        if (!$it && $this->topic) {
            return $this->topic->isA($class);
        }
        return $it;
    }
}
