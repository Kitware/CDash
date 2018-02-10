<?php
namespace CDash\Messaging\Notification;

use CDash\Collection\Collection;

class NotificationCollection extends Collection
{
    public function add(NotificationInterface $notification)
    {
        parent::addItem($notification);
    }
}
