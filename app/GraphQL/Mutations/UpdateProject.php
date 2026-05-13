<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class UpdateProject extends AbstractMutation
{
    public ?Project $project = null;

    /**
     * @param array<string,mixed> $args
     */
    public function __invoke(null $_, array $args): self
    {
        $project = Project::find((int) $args['id']);

        Gate::authorize('update', $project);

        unset($args['id']);

        $project?->update($args);

        $this->project = $project;

        $user = auth()->user();
        Log::info("User {$user?->id} updated project {$project?->id}.");

        return $this;
    }
}
