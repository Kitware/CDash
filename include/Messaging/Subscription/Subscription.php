<?php
namespace CDash\Messaging\Subscription;

use CDash\Config;
use CDash\Messaging\Notification\NotificationInterface;
use CDash\Messaging\Topic\TopicCollection;
use Build;
use Project;
use SubscriberInterface;

class Subscription implements SubscriptionInterface
{
    protected static $max_display_items = 5;

    /** @var  SubscriberInterface $subscriber */
    private $subscriber;

    /** @var  TopicCollection $topicCollection */
    private $topicCollection;

    /** @var  NotificationInterface $notification */
    private $notification;

    /** @var  Project $project */
    private $project;

    /**
     * @param SubscriberInterface $subscriber
     * @return Subscription
     */
    public function setSubscriber(SubscriberInterface $subscriber)
    {
        $this->subscriber = $subscriber;
        return $this;
    }

    /**
     * @param TopicCollection $topicCollection
     * @return Subscription
     */
    public function setTopicCollection(TopicCollection $topicCollection)
    {
        $this->topicCollection = $topicCollection;
        return $this;
    }

    /**
     * @return SubscriberInterface
     */
    public function getSubscriber()
    {
        return $this->subscriber;
    }

    /**
     * @return TopicCollection
     */
    public function getTopicCollection()
    {
        return $this->topicCollection;
    }

    /**
     * @return TopicCollection
     */
    public function getTopics()
    {
        return $this->getTopicCollection();
    }

    /**
     * @param NotificationInterface $notification
     * @return Subscription
     */
    public function setNotification(NotificationInterface $notification)
    {
        $this->notification = $notification;
        return $this;
    }

    /**
     * @return NotificationInterface;
     */
    public function getNotification()
    {
        return $this->notification;
    }

    /**
     * @return string
     */
    public function getSender()
    {
        return Config::getInstance()->get('CDASH_NOTIFICATION_SENDER');
    }

    /**
     * @return string
     */
    public function getRecipient()
    {
        return $this->subscriber->getAddress();
    }

    /**
     * @param Project $project
     * @return Subscription
     */
    public function setProject(Project $project)
    {
        $this->project = $project;
        return $this;
    }

    /**
     * @return Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @return int
     */
    public static function getMaxDisplayItems()
    {
        return self::$max_display_items;
    }

    /**
     * @param $max_display_items
     */
    public static function setMaxDisplayItems($max_display_items)
    {
        // $max_display_items must always have an integer value > zero
        if (is_int($max_display_items) && $max_display_items > 0) {
            self::$max_display_items = $max_display_items;
        }
    }

    /**
     * @return string
     */
    public function getBuildSummary()
    {
        // TODO: Implement getBuildSummary() method.
    }
}
