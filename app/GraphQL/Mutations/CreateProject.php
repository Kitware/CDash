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
        return ProjectService::create($args);
    }
}
