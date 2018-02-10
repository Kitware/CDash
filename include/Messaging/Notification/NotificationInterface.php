<?php
namespace CDash\Messaging\Notification;

use CDash\Messaging\Notification\Email\Decorator\DecoratorInterface;

interface NotificationInterface
{
    public function setSender($sender);
    public function setRecipient($recipient);
    public function getRecipient();
    public function setBody(DecoratorInterface $body);
    public function getBody();
    public function setSubject($subject);
    public function getSubject();
}
