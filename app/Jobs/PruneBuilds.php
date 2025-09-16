<?php

namespace App\Jobs;

use App\Models\Project;
use App\Utils\DatabaseCleanupUtils;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deletes builds older than the configured system, project, and buildgroup autoremove timeframes.
 */
class PruneBuilds implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        foreach (Project::all() as $project) {
            DatabaseCleanupUtils::removeFirstBuilds($project->id, $project->autoremovetimeframe, $project->autoremovemaxbuilds);
            DatabaseCleanupUtils::removeBuildsGroupwise($project->id, $project->autoremovemaxbuilds);
        }
    }
}
