<?php
namespace CDash\Messaging\Topic;

use CDash\Model\Build;
use CDash\Model\SubscriberInterface;

interface TopicInterface
{
    /**
     * @param Build $build
     * @return bool
     */
    public function subscribesToBuild(Build $build);

    /**
     * @param Build $build
     * @return $this
     */
    public function addBuild(Build $build);

    /**
     * @param SubscriberInterface $subscriber
     * @return $this
     */
    public function setSubscriber(SubscriberInterface $subscriber);

    /**
     * @param Build $build
     * @return $this
     */
    public function setTopicData(Build $build);

    /**
     * @return mixed
     */
    public function getTopicCollection();

    /**
     * @return string
     */
    public function getTopicDescription();

    /**
     * @return int
     */
    public function getTopicCount();

    /**
     * @param $item
     * @return boolean
     */
    public function itemHasTopicSubject(Build $build, $item);

    /**
     * @return string|array
     */
    public function getTemplate();
}
