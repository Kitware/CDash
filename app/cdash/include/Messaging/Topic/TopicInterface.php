<?php

namespace CDash\Messaging\Topic;

use CDash\Model\Build;
use CDash\Model\Subscriber;

interface TopicInterface
{
    /**
     * @return bool
     */
    public function subscribesToBuild(Build $build);

    /**
     * @return $this
     */
    public function addBuild(Build $build);

    /**
     * @return $this
     */
    public function setSubscriber(Subscriber $subscriber);

    /**
     * @return $this
     */
    public function setTopicData(Build $build);

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
     * @return bool
     */
    public function itemHasTopicSubject(Build $build, $item);

    /**
     * @return string|array
     */
    public function getTemplate();
}
