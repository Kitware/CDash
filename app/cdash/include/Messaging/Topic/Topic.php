<?php

namespace CDash\Messaging\Topic;

use CDash\Collection\BuildCollection;
use CDash\Collection\Collection;
use CDash\Model\Build;
use CDash\Model\Subscriber;

abstract class Topic implements TopicInterface
{
    public const BUILD_ERROR = 'BuildError';
    public const BUILD_WARNING = 'BuildWarning';
    public const CONFIGURE = 'Configure';
    public const DYNAMIC_ANALYSIS = 'DynamicAnalysis';
    public const LABELED = 'Labeled';
    public const TEST_FAILURE = 'TestFailure';
    public const TEST_MISSING = 'TestMissing';
    public const UPDATE_ERROR = 'UpdateError';

    /** @var Subscriber */
    protected $subscriber;

    protected $topicData;

    /** @var BuildCollection */
    private $buildCollection;

    /** @var Topic */
    protected $topic;

    /** @var string[] */
    protected $labels;

    /**
     * Topic constructor.
     *
     * @param TopicInterface|Fixable|Labelable|null $topic
     */
    public function __construct(?TopicInterface $topic = null)
    {
        $this->topic = $topic;
    }

    public function addBuild(Build $build): static
    {
        $collection = $this->getBuildCollection();
        $collection->add($build);
        $this->setTopicData($build);
        return $this;
    }

    public function setSubscriber(Subscriber $subscriber): static
    {
        $this->subscriber = $subscriber;
        if ($this->topic) {
            $this->topic->setSubscriber($subscriber);
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function setTopicData(Build $build)
    {
        if ($this->topic) {
            $this->topic->setTopicData($build);
        }
        return $this;
    }

    public function itemHasTopicSubject(Build $build, $item): bool
    {
        $hasTopic = false;
        if ($this->topic) {
            $hasTopic = $this->topic->itemHasTopicSubject($build, $item);
        }
        return $hasTopic;
    }

    /**
     * @return Collection
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
     */
    public function getTopicName(): string
    {
        $name = '';
        if ($this->topic) {
            $name = $this->topic->getTopicName();
        }
        return $name;
    }

    public function getTopicDescription(): string
    {
        if ($this->topic) {
            return $this->topic->getTopicDescription();
        }
        return '';
    }

    public function getBuildCollection(): BuildCollection
    {
        if (!$this->buildCollection) {
            $this->buildCollection = new BuildCollection();
        }

        return $this->buildCollection;
    }

    public function getLabels(): array
    {
        if ($this->topic) {
            return $this->topic->getLabels();
        }
        return [];
    }

    public function getTopicCount(): int
    {
        if ($this->topic) {
            return $this->topic->getTopicCount();
        }
        return 0;
    }

    /**
     * @param null $category
     */
    public function hasSubscriberAlreadyBeenNotified(Build $build, $category = null): bool
    {
        $collection = $build->GetBuildEmailCollection();
        $address = $this->subscriber->getAddress();

        if (null !== $category) {
            $collection = $collection
                ->sortByCategory()
                ->get($category);
        }

        return $collection && $collection->has($address);
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
        $templates = [];
        if ($this->topic) {
            $templates = $this->topic->getTemplate();
        }
        return $templates;
    }
}
