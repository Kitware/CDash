<?php
namespace CDash\archive\Messaging\Email\Decorator;


use CDash\Messaging\Collection\RecipientCollection;
use CDash\Messaging\DecoratorInterface;
use CDash\Messaging\Email\EmailMessage;
use CDash\Messaging\Collection\Collection;
use CDash\Messaging\MessageInterface;

interface EmailDecoratorInterface extends DecoratorInterface
{
    /**
     * EmailDecoratorInterface constructor.
     * @param Collection|null $topicCollection
     * @param RecipientCollection|null $recipientCollection
     */
    public function __construct(
        Collection $topicCollection = null,
        RecipientCollection $recipientCollection = null
    );

    /**
     * @param EmailMessage $message
     * @return EmailDecoratorInterface
     */
    public function setMessage(MessageInterface $message);

    /**
     * This returns true if the decorator has content (its topic) to add to a message, e.g. errors,
     * false otherwise.
     * @return boolean
     */
    public function hasTopic();

    /**
     * This returns true if the decorator has recipients subscribed to its topic
     * @return boolean
     */
    public function hasRecipients();

    /**
     * This returns true if the $userLabels argument intersects with any topic labels
     * @return boolean
     */
    public function hasLabels(array $userLabels);

    /**
     * Returns the topic of the email, e.g. dynamic analysis, or test failure, etc.
     * @return string
     */
    public function getTopicName();

    /**
     * Method to decorate the body of the message
     * @return string
     */
    public function body();

    /**
     * Method to decorate the subject of the message
     * @return string
     */
    public function subject();
}
