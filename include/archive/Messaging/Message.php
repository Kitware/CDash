<?php
namespace CDash\archive\Messaging;

use BuildGroup;
use CDash\Messaging\Collection\BuildCollection;
use CDash\Messaging\Collection\DecoratorCollection;
use CDash\Messaging\Collection\RecipientCollection;
use Project;
/**
 * Message
 * TODO: rename class Notify
 */
abstract class Message implements MessageInterface
{
    /** @var Project $project */
    protected $project;

    /** @var BuildGroup $buildGroup */
    protected $buildGroup;

    /** @var BuildCollection $buildCollection */
    protected $buildCollection;

    /** @var DecoratorCollection $decoratorCollection */
    protected $decoratorCollection;

    /** @var  RecipientCollection $recipientCollection */
    protected $recipientCollection;

    protected $transport;

    /**
     * Message constructor.
     * @param DecoratorCollection|null $decoratorCollection
     */
    public function __construct(DecoratorCollection $decoratorCollection)
    {
        $this->decoratorCollection = $decoratorCollection;
    }

    /**
     * @param Project $project
     * @return Message
     */
    public function setProject(Project $project)
    {
        $this->project = $project;
        return $this;
    }

    /**
     * @param BuildGroup $buildGroup
     * @return Message
     */
    public function setBuildGroup(BuildGroup $buildGroup)
    {
        $this->buildGroup = $buildGroup;
        return $this;
    }

    public function getBuildGroup()
    {
        return $this->buildGroup;
    }

    /**
     * @param BuildCollection $buildCollection
     * @return Message
     */
    public function setBuildCollection(BuildCollection $buildCollection) {
        $this->buildCollection = $buildCollection;
        return $this;
    }

    /**
     * @param DecoratorCollection $decoratorCollection
     * @return Message
     */
    public function setDecoratorCollection(DecoratorCollection $decoratorCollection)
    {
        $this->decoratorCollection = $decoratorCollection;
        return $this;
    }

    /**
     * @return DecoratorCollection|null
     */
    public function getDecoratorCollection()
    {
        return $this->decoratorCollection;
    }

    /**
     * Returns true if any of its decorators contains content for its topic.
     * @return boolean
     */
    public function hasTopic()
    {
        $hasTopic = false;
        foreach ($this->decoratorCollection as $decorator) {
            if ($decorator->hasTopic() && !$hasTopic) {
                $hasTopic = true;
            }
        }

        return $hasTopic;
    }

    public function hasRecipients()
    {
        $hasRecipients = false;
        foreach ($this->decoratorCollection as $decorator) {
            if ($decorator->hasRecipients() && !$hasRecipients) {
                $hasRecipients = true;
            }
        }
        return $hasRecipients;
    }

    /**
     * @return BuildCollection
     */
    public function getBuilds()
    {
        return $this->buildCollection;
    }

    public function send()
    {
        $messages = [];
        if ($this->hasTopic() && $this->hasRecipients()) {
            $messages = $this->getMessages();
            foreach ($messages as $message) {
                $this->transport->send($message);
            }
        }
    }
}
