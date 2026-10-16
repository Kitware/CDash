<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Utils\TestingDay;
use Illuminate\View\View;

final class ProjectHomeController extends AbstractProjectController
{
    public function __invoke(int $project_id): View
    {
        $this->setProjectById($project_id);

        $eloquentProject = Project::findOrFail((int) $this->project->Id);

        // Link to the most recent testing day with builds, since today's dashboard may be empty.
        /** @var ?string $latestStartTime */
        $latestStartTime = $eloquentProject->builds()->max('starttime');
        $dashboardDate = $latestStartTime === null ? null : TestingDay::get($this->project, $latestStartTime);

        /** @var ?User $user */
        $user = auth()->user();

        return $this->vue('project-home-page', 'Home', [
            'project-id' => $this->project->Id,
            'dashboard-date' => $dashboardDate,
            'testing-day-start' => $this->project->NightlyDateTime?->format('H:i e'),
            'can-update' => $user?->can('update', $eloquentProject) ?? false,
        ]);
    }
}
