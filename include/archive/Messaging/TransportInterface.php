<?php
namespace CDash\archive\Messaging;

interface TransportInterface
{
    /**
     * @param MessageInterface $message
     * @return bool
     */
    public function send(MessageInterface $message);
}
