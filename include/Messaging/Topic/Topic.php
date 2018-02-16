<?php
namespace CDash\Messaging\Topic;

use Build;
use CDash\Collection\BuildCollection;
use CDash\Collection\CollectionInterface;
use SubscriberInterface;

abstract class Topic implements TopicInterface
{
    const TOPIC_NEVER    = 0;
    const TOPIC_FILTERED = 1;                                    // 2^0
    const TOPIC_UPDATE = 2;                                      // 2^1
    const TOPIC_CONFIGURE = 4;                                   // 2^2
    const TOPIC_WARNING = 8;                                     // 2^3
    const TOPIC_ERROR = 16;                                      // 2^4
    const TOPIC_TEST = 32;                                       // 2^5
    const TOPIC_DYNAMIC_ANALYSIS = 64;                           // 2^6
    const TOPIC_FIXES = 128;                                     // 2^7
    const TOPIC_MISSING_SITES = 256;                             // 2^8

    // NEW MASKS
    const TOPIC_USER_CHECKIN_ISSUE_ANY_SECTION = 512;            // 2^9
    const TOPIC_ANY_USER_CHECKIN_ISSUE_NIGHTLY_SECTION = 1024;   // 2^10
    const TOPIC_ANY_USER_CHECKIN_ISSUE_ANY_SECTION = 2048;       // 2^11
    const TOPIC_USER_CHECKIN_FIX = 4096;                         // 2^12
    const TOPIC_EXPECTED_SITE_NOT_SUBMITTING = 8192;             // 2^12

    /** @var  SubscriberInterface $subscriber */
    protected $subscriber;

    /** @var  mixed $topicData */
    protected $topicData;

    /** @var  Build $build */
    private $build;

    /** @var  BuildCollection */
    private $buildCollection;

    public function addBuild(Build $build)
    {
        $collection = $this->getBuildCollection();
        $collection->add($build);
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
        return $this;
    }

    /**
     * @param $topicData
     * @return Topic
     */
    public function setTopicData($topicData)
    {
        $this->topicData = $topicData;
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
        $start = strlen(__NAMESPACE__) + 1; // +1 for trailing separator
        $end = -strlen(substr(self::class, $start)); // abstract class name
        return substr(static::class, $start, $end);
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
}
