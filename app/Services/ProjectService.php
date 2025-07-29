<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Gate;

class ProjectService extends AbstractService
{
    /**
     * @param array<string,mixed> $attributes
     */
    public static function create(array $attributes): Project
    {
        $project = Project::create($attributes);

        $legacy_project = new \CDash\Model\Project();
        $legacy_project->Id = $project->id;
        $legacy_project->InitialSetup();
        $project->refresh();

        return $project;
    }
}
