<?php
namespace CDash\Messaging\Subscription;

use CDash\Messaging\Notification\NotificationBuilderInterface;

interface SubscriptionNotificationBuilderInterface extends NotificationBuilderInterface
{
    /**
     * @param SubscriptionCollection $subscriptions
     * @return mixed
     */
    public function setSubscriptions(SubscriptionCollection $subscriptions);
}
