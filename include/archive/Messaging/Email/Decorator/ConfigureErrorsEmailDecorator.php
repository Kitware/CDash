<?php
namespace CDash\archive\Messaging\Email\Decorator;

class ConfigureErrorsEmailDecorator extends EmailDecorator
{
    /**
     * @return string
     */
    public function body()
    {
        // TODO: Implement body() method.
    }

    /**
     * @return string
     */
    public function subject()
    {
        // TODO: Implement subject() method.
    }

    /**
     * This returns true if the decorator has content (its topic) to add to a message, e.g. errors,
     * false otherwise.
     * @return boolean
     */
    public function hasTopic()
    {
        // TODO: Implement hasTopic() method.
    }

    /**
     * This returns true if the $userLabels argument intersects with any topic labels
     * @return boolean
     */
    public function hasLabels(array $userLabels)
    {
        // TODO: Implement hasLabels() method.
    }

    /**
     * Returns the topic of the email, e.g. dynamic analysis, or test failure, etc.
     * @return string
     */
    public function getTopicName()
    {
        // TODO: Implement getTopicName() method.
    }
}
