<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Messaging\Topic\Topic;

interface DecoratorInterface
{
    /**
     * @param Topic $topic
     * @return string|void
     */
    public function setTopic(Topic $topic);

    /**
     * @return string
     */
    public function __toString();
}
