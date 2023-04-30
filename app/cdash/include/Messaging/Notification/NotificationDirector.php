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

        /* @var \CDash\Messaging\Subscription\Subscription $subscription */
        foreach ($subscriptions as $recipient => $subscription) {
            foreach ($subscription->getTopicTemplates() as $template) {
                $notification = $builder->createNotification($subscription, $template);
                if ($notification) {
                    $notifications->add($notification);
                }
            }
        }
        return $notifications;
    }
}
