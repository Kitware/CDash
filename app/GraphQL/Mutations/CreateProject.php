<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Support\Facades\Gate;

class CreateProject
{
    /**
     * @param array<string,mixed> $args
     */
    public function __invoke(null $_, array $args): Project
    {
        Gate::authorize('create', Project::class);

        $project = ProjectService::create($args);

        $project->users()->attach(auth()->user()?->id, [
            'emailtype' => 0,
            'emailcategory' => 62,
            'emailsuccess' => false,
            'emailmissingsites' => false,
            'role' => Project::PROJECT_ADMIN,
        ]);

        return $project;
    }
}
