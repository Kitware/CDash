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
     * @param SubscriberInterface $subscriber
     * @return self
     */
    public function setSubscriber(SubscriberInterface $subscriber);

    /**
     * @param $data
     * @return mixed
     */
    public function setTopicData($data);

    /**
     * @return mixed
     */
    public function getTopicData();

    /**
     * @return string
     */
    public function getTopicDescription();
}
