<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Messaging\Topic\TopicInterface;

interface DecoratorInterface
{
    public function setDecorator(DecoratorInterface $decorator);
    public function setTemplateData($data);
    public function decorateWith(array $topic);
    public function __toString();
}
