<?php
namespace CDash\Messaging\Notification\Email;

use CDash\Messaging\FactoryInterface;

class EmailNotificationFactory implements FactoryInterface
{
    /**
     * @return EmailMessage
     */
    public function create()
    {
        return new EmailMessage();
    }
}
