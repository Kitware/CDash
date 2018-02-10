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
     * @param ActionableBuildInterface $actionableBuild
     * @return bool
     */
    public function hasBuildTopics(ActionableBuildInterface $actionableBuild)
    {
        $hasBuildTopics = false;
        $topics = $this->getTopics();
        /** @var Build $build */
        foreach ($actionableBuild->getActionableBuilds() as $build) {
            /** @var \CDash\Messaging\Topic\Topic|CancelationInterface $topic */
            foreach (TopicFactory::createFrom($this->preferences) as $topic) {
                if (is_a($topic, CancelationInterface::class)) {
                    if ($topic->subscribesToBuild($build) === false) {
                        return false;
                    }
                } else {
                    if ($topic->subscribesToBuild($build)) {
                        $hasBuildTopics = true;
                        $topic->setBuild($build);
                        $topics->add($topic);
                    }
                }
            }
        }
        return $hasBuildTopics;
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
