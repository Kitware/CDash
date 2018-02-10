<?php
namespace CDash\Messaging\Notification\Email;

use CDash\Messaging\Notification\Email\Decorator\DecoratorInterface;
use CDash\Messaging\Notification\NotificationInterface;

class EmailMessage implements NotificationInterface
{
    /** @var  string $sender */
    protected $sender;

    /** @var  string $recipient */
    protected $recipient;

    /** @var  string $subject */
    protected $subject;

    /** @var  DecoratorInterface $body */
    private $body;

    /**
     * @param $sender
     * @return EmailMessage
     */
    public function setSender($sender)
    {
        $this->sender = $sender;
        return $this;
    }

    /**
     * @return string
     */
    public function getSender()
    {
        return $this->sender;
    }

    /**
     * @param $recipient
     * @return EmailMessage
     */
    public function setRecipient($recipient)
    {
        $this->recipient = $recipient;
        return $this;
    }

    /**
     * @return string
     */
    public function getRecipient()
    {
        return $this->recipient;
    }

    /**
     * @param DecoratorInterface $body
     * @return EmailMessage
     */
    public function setBody(DecoratorInterface $body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @param $subject
     * @return EmailMessage
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return "{$this->body}";
    }

    /**
     * @return string
     * TODO: this should probably return all headers + body see (RFC 2822)
     */
    public function __toString()
    {
        return $this->body;
    }
}
