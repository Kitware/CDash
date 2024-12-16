<?php
namespace CDash\Messaging\Notification\Email;

class EmailNotificationFactory
{
    /**
     * @return EmailMessage
     */
    public function create()
    {
        return new EmailMessage();
    }
}
