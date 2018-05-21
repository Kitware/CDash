<?php

use CDash\Collection\BuildCollection;
use CDash\Model\Build;
use CDash\Model\Project;

/**
 * ActionableHandler
 */
interface ActionableBuildInterface
{
    /**
     * @return Build[]
     * @deprecated Use GetBuildCollection() 02/04/18
     */
    public function getActionableBuilds();

    /**
     * @return BuildCollection
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function GetBuildCollection();

    /**
     * @return Project
     */
    public function GetProject();
}
