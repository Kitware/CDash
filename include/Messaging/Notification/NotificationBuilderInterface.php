<?php
namespace CDash\Messaging\Notification;

use CDash\Collection\Collection;
use CDash\Collection\CollectionInterface;
use CDash\Collection\SubscriberCollection;
use CDash\Messaging\FactoryInterface;
use CDash\Messaging\Subscription\SubscriptionInterface;

interface NotificationBuilderInterface
{
    /**
     * NotificationBuilderInterface constructor.
     * @param FactoryInterface $factory
     * @param CollectionInterface $collection
     */
    public function __construct(FactoryInterface $factory, CollectionInterface $collection);

    /**
     * @return NotificationInterface
     */
    public function createNotification(SubscriptionInterface $subscription);

    /**
     * @return SubscriberCollection
     */
    public function getSubscriptions();

    /**
     * @return NotificationCollection
     */
    public function getNotifications();
}
