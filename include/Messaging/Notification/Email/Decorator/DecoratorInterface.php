<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Messaging\Notification\Email\EmailMessage;

interface DecoratorInterface
{
    public function setDecorator(DecoratorInterface $decorator);
    public function decorateWith(array $topic);
    public function __toString();
}
