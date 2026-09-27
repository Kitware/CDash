<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Jobs\DeleteProject as DeleteProjectJob;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class DeleteProject extends AbstractMutation
{
    /**
     * @param array{
     *     projectId: int,
     * } $args
     */
    public function __invoke(null $_, array $args): self
    {
        $projectId = (int) $args['projectId'];
        $project = Project::find($projectId);

        Gate::authorize('delete', $project);

        DeleteProjectJob::dispatch($projectId);

        $user = auth()->user();
        Log::info("User {$user?->id} queued deletion of project {$project?->name} ({$projectId}).");

        return $this;
    }
}
