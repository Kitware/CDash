<?php
namespace CDash\archive\Messaging\Email;

use CDash\Messaging\MessageInterface;
use CDash\Messaging\TransportInterface;

class EmailTransport implements TransportInterface
{
    /**
     * @param MessageInterface $message
     * @return bool
     */
    public function send(MessageInterface $message)
    {
        $messages = $message->getMessages();
    }
}
