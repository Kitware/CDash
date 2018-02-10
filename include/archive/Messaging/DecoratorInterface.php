<?php
namespace CDash\archive\Messaging;

interface DecoratorInterface
{
    /**
     * @param MessageInterface $message
     * @return void
     */
    public function setMessage(MessageInterface $message);
}
