<?php
namespace CDash\Messaging\Topic;

use Build;
use CDash\Messaging\Email\Decorator\DecoratorInterface;
use SubscriberInterface;

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
    public function getTopicData();

    /**
     * @return string
     */
    public function getTopicDescription();
}
