<?php

namespace CDash\Messaging\Notification;

class NotificationDirector
{
    /**
     * @param NotificationBuilderInterface $builder
     * @return NotificationCollection
     */
    public function build(NotificationBuilderInterface $builder)
    {
        $subscriptions = $builder->getSubscriptions();
        $notifications = $builder->getNotifications();

        foreach ($subscriptions as $recipient => $subscription) {
            $notification = $builder->createNotification($subscription);
            if ($notification) {
                $notifications->add($notification);
            }
        }
        return $notifications;
    }
}
