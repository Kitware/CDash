<?php

namespace CDash\Messaging\Notification;

class NotificationDirector
{
    public function build(NotificationBuilderInterface $builder): NotificationCollection
    {
        $subscriptions = $builder->getSubscriptions();
        $notifications = $builder->getNotifications();

        /* @var \CDash\Messaging\Subscription\Subscription $subscription */
        foreach ($subscriptions as $recipient => $subscription) {
            foreach ($subscription->getTopicTemplates() as $template) {
                $notifications->add($builder->createNotification($subscription, $template));
            }
        }
        return $notifications;
    }
}
