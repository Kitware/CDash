<?php

namespace Tests\Traits;

use \CDash\Model\Project;

trait CreatesProjects
{
    public function makePublicProject(): Project
    {
        $project = new Project();
        $project->Name = 'PublicProject';
        $project->Public = Project::ACCESS_PUBLIC;
        $project->Save();
        return $project;
    }
}
