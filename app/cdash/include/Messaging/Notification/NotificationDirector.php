<?php

namespace CDash\Messaging\Notification;

use CDash\Messaging\Notification\Email\EmailBuilder;

class NotificationDirector
{
    public function build(EmailBuilder $builder): NotificationCollection
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
