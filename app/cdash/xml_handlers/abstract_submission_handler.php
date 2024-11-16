<?php

use CDash\Model\Build;
use CDash\Model\Project;

abstract class AbstractSubmissionHandler
{
    protected Build $Build;

    protected Project $Project;

    /**
     * We prefer to accept a Build if one is known, but some (mostly XML) handlers determine the build
     * themselves by inspecting the XML.  In such cases, we provide only a project.  This approach is
     * not ideal and should be improved in the future for better type safety.
     */
    public function __construct(Build|Project $init)
    {
        if ($init instanceof Project) {
            $this->Project = $init;
            $this->Build = new Build();
        } else {
            $this->Build = $init;
            $this->Build->FillFromId($this->Build->Id);
            $this->Project = $this->Build->GetProject();
        }

        $this->Project->Fill();
    }

    /**
     * TODO: This was copied here from legacy code.  In the future, we should re-evaluate
     *       whether this should be in the abstract handler or not, given that many handlers
     *       override it.
     *
     * @return Build[]
     */
    public function getBuilds(): array
    {
        return [$this->Build];
    }
}
