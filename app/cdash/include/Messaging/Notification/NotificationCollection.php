<?php

namespace CDash\Messaging\Notification;

use CDash\Collection\Collection;
use CDash\Messaging\Notification\Email\EmailMessage;

class NotificationCollection extends Collection
{
    public function add(EmailMessage $notification)
    {
        parent::addItem($notification, $notification->getRecipient());
    }
}
