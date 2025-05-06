<?php

use CDash\Model\Build;
use CDash\Model\Project;

abstract class AbstractSubmissionHandler
{
    protected Build $Build;

    private ?Project $Project = null;

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
        }
    }

    public function GetProject(): Project
    {
        if ($this->Project === null) {
            $this->Project = $this->Build->GetProject();
        }

        $this->Project->Fill();
        return $this->Project;
    }

    public function getBuild(): Build
    {
        return $this->Build;
    }

    /**
     * Accepts an absolute path to an input submission file.
     *
     * Returns an array of validation messages (if applicable).  An empty return array indicates
     * that validation was successful.  Returns an empty array if no validation was defined.
     *
     * @return array<string>
     */
    public static function validate(string $path): array
    {
        return [];
    }
}
