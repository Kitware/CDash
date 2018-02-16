<?php
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Messaging\Topic\CancelationInterface;
use CDash\Messaging\Topic\TopicCollection;
use CDash\Messaging\Topic\TopicFactory;

class Subscriber implements SubscriberInterface
{
    /** @var  NotificationPreferences $preferences */
    private $preferences;

    /** @var  string $address */
    private $address;

    /** @var TopicCollection $topics */
    private $topics;

    /** @var  string[] */
    private $labels;


    /**
     * SubscriberInterface constructor.
     * @param NotificationPreferences $preferences
     * @param TopicCollection|null $topics
     */
    public function __construct(
        NotificationPreferences $preferences,
        TopicCollection $topics = null
    ) {
        $this->preferences = $preferences;
        $this->topics = $topics;
    }

    /**
     * @param ActionableBuildInterface $submission
     * @return bool
     */
    public function hasBuildTopics(ActionableBuildInterface $submission)
    {
        $topics = $this->getTopics();
        $builds = $submission->GetBuildCollection();
        $user_topics = TopicFactory::createFrom($this->preferences);

        foreach ($user_topics as $topic) {
            $topic->setSubscriber($this);
            foreach ($builds as $build) {
                if (is_a($topic, CancelationInterface::class)) {
                    if ($topic->subscribesToBuild($build) === false) {
                        return false;
                    }
                    continue;
                }
                if ($topic->subscribesToBuild($build)) {
                    $topic->addBuild($build);
                    if (!$topics->has($topic->getTopicName())) {
                        $topics->add($topic);
                    }
                }
            }
        }
        return $topics->count() > 0;
    }

    protected function initializeTopics()
    {
        $topics = $this->getTopics();
        foreach (TopicFactory::createFrom($this->preferences) as $topic) {
            $topics->add($topic);
        }
        return (bool) count($topics);
    }

    /**
     * @return TopicCollection
     */
    public function getTopics()
    {
        if (is_null($this->topics)) {
            $this->topics = new TopicCollection();
        }
        return $this->topics;
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param $address
     * @return Subscriber
     */
    public function setAddress($address)
    {
        $this->address = $address;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * @param array $labels
     * @return Subscriber
     */
    public function setLabels(array $labels)
    {
        $this->labels = $labels;
        return $this;
    }
}
