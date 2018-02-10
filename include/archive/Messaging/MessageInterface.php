<?php
namespace CDash\archive\Messaging;

use BuildGroup;
use CDash\Messaging\Collection\BuildCollection;
use Project;

interface MessageInterface
{
    const TYPE_EMAIL = 'EMAIL';

    /**
     * @return boolean
     */
    public function send();

    /**
     * @return \CDash\Messaging\Collection\BuildCollection
     */
    public function getBuilds();

    /**
     * @param Project $project
     * @return MessageInterface
     */
    public function setProject(Project $project);

    /**
     * @param BuildGroup $buildGroup
     * @return MessaegeInterface
     */
    public function setBuildGroup(BuildGroup $buildGroup);

    /**
     * @return BuildGroup;
     */
    public function getBuildGroup();

    /**
     * @param BuildCollection $buildCollection
     * @return MessageInterface
     */
    public function setBuildCollection(BuildCollection $buildCollection);

    /**
     * @param DecoratorInterface $decorator
     * @return MessageInterface
     */
    public function addDecorator(DecoratorInterface $decorator);

    /**
     * @return mixed
     */
    public function getMessages();

    /**
     * Returns true if this message has recipients. Recipients are determined by to factors:
     *   1] Does the message have content, for example, if it is an update are there errors?
     *   2] Does the user's email preferences match the content of the email
     * @return boolean
     */
    public function hasRecipients();

    /**
     * Returns true if any of its decorators contains content for its topic
     * @return boolean
     */
    public function hasTopic();
}
