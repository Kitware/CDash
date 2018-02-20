<?php
namespace CDash\Messaging\Notification\Email\Decorator;

interface DecoratorInterface
{
    public function addSubject($subject);
    public function __toString();
}
