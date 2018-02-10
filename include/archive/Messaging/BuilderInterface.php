<?php
namespace CDash\archive\Messaging;

use ActionableBuildInterface;
use CDash\Messaging\Collection\BuildCollection;
use Project;
use BuildGroup;

interface BuilderInterface
{
    /**
     * BuilderInterface constructor.
     * @param ActionableBuildInterface $actionableBuild
     */
    public function __construct(ActionableBuildInterface $actionableBuild);

    /**
     * @return BuilderInterface
     */
    public function createMessage();

    /**
     * @return \CDash\Messaging\MessageInterface
     */
    public function getMessage();

    /**
     * @return BuilderInterface
     */
    public function addProject();

    /**
     * @return BuilderInterface
     */
    public function addBuildGroup();

    /**
     * @return BuilderInterface
     */
    public function addBuildCollection();

    /**
     * @return BuilderInterface
     */
    public function addDecoratorsToCollection();

}
