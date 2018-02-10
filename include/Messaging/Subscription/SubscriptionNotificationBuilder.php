<?php
namespace CDash\Messaging\Subscription;

use CDash\Messaging\FactoryInterface;
use CDash\Collection\CollectionInterface;
use CDash\Messaging\Subscription\SubscriptionCollection;

/**
 * SubscriptionNotificationBuilder is an abstract class meant to be extended by specific types of
 * notifications in this package (e.g. email, or future implementations, say Slack). It initializes
 * the following properties for it's child classes:
 *   $factory : Returns instances of notifications, e.g. new Email()
 *   $notifications: A container for the notifications
 *   $subscriptions: The subscriptions with data to create the notifications
 *
 * Class SubscriptionNotificationBuilder
 * @package CDash\Messaging\Notification
 */
abstract class SubscriptionNotificationBuilder implements SubscriptionNotificationBuilderInterface
{
    /**
     * @var FactoryInterface $factory
     */
    protected $factory;

    /**
     * @var NotificationCollection $notifications
     */
    protected $notifications;

    /**
     * @var SubscriptionCollection
     */
    protected $subscriptions;

    /**
     * NotificationBuilderInterface constructor.
     * @param FactoryInterface $factory
     * @param CollectionInterface $collection
     */
    public function __construct(FactoryInterface $factory, CollectionInterface $collection)
    {
        $this->factory = $factory;
        $this->notifications = $collection;
    }

    /**
     * Sets a SubscriptionCollection
     *
     * @param SubscriptionCollection $subscription
     * @return SubscriptionNotificationBuilderInterface
     */
    public function setSubscriptions(SubscriptionCollection $subscriptions)
    {
        $this->subscriptions = $subscriptions;
        return $this;
    }
}
