<?php

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
     * @return Project
     */
    public function GetProject();
}
