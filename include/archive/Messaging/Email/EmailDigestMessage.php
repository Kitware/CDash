<?php
namespace CDash\archive\Messaging\Email;

use CDash\Messaging\DecoratorInterface;
use CDash\Messaging\Message;
use CDash\Messaging\MessageInterface;

class EmailDigestMessage extends Message
{

    /**
     * Returns true if this message has recipients. Recipients are determined by to factors:
     *   1] Does the message have content, for example, if it is an update are there errors?
     *   2] Does the user's email preferences match the content of the email
     * @return boolean
     */
    public function hasRecipients()
    {
        // TODO: Implement hasRecipients() method.
    }

    /**
     * @param DecoratorInterface $decorator
     * @return MessageInterface
     */
    public function addDecorator(DecoratorInterface $decorator)
    {
        // TODO: Implement addDecorator() method.
    }

    /**
     * @return mixed
     */
    public function getMessages()
    {
        // TODO: Implement getMessages() method.
    }
}
