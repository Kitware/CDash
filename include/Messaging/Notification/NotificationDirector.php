<?php

namespace CDash\Messaging\Notification;

class NotificationDirector
{
    public function build(NotificationBuilderInterface $builder)
    {
        /*
        $builder->createNotification();
        $builder->addTopics();
        $builder->addSummary();
        $builder->addPreamble();
        $builder->addSubject();

        $builder->addDeliveryInformation();
        */

        return $builder->createNotifications();
    }
}
