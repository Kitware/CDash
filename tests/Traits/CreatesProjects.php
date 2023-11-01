<?php

namespace Tests\Traits;

use CDash\Model\Project;
use Illuminate\Support\Str;

trait CreatesProjects
{
    public function makePublicProject(?string $name = null): Project
    {
        $project = new Project();
        $project->Name = $name ?? 'PublicProject_' . Str::uuid()->toString();
        $project->Public = Project::ACCESS_PUBLIC;
        $project->Save();
        $project->InitialSetup();
        return $project;
    }

    public function makeProtectedProject(?string $name = null): Project
    {
        $project = new Project();
        $project->Name = $name ?? 'ProtectedProject_' . Str::uuid()->toString();
        $project->Public = Project::ACCESS_PROTECTED;
        $project->Save();
        $project->InitialSetup();
        return $project;
    }

    public function makePrivateProject(?string $name = null): Project
    {
        $project = new Project();
        $project->Name = $name ?? 'PrivateProject_' . Str::uuid()->toString();
        $project->Public = Project::ACCESS_PRIVATE;
        $project->Save();
        $project->InitialSetup();
        return $project;
    }
}
