<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Permanently deletes a project and all of its associated data.  Queued because a project with a
 * large build history can take a long time to clean up.
 */
class DeleteProject implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public int $projectId,
    ) {
    }

    public function handle(): void
    {
        $project = Project::find($this->projectId);
        if ($project === null) {
            return;
        }

        ProjectService::delete($project);
    }
}
