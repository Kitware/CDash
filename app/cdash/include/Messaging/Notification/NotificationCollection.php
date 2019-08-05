<?php
namespace CDash\Messaging\Notification;

use CDash\Collection\Collection;

class NotificationCollection extends Collection
{
    /**
     * @param NotificationInterface $notification
     */
    public function add(NotificationInterface $notification)
    {
        parent::addItem($notification, $notification->getRecipient());
    }
}
