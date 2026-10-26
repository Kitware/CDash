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

        // The time zone which TestingDay compares times in.  See Project::SetNightlyTime().
        $nightlyTimezone = $this->project->NightlyTimezone?->getName() ?? 'UTC';
        $timezoneFromAbbreviation = timezone_name_from_abbr($nightlyTimezone);
        if ($timezoneFromAbbreviation !== false) {
            $nightlyTimezone = $timezoneFromAbbreviation;
        }

        /** @var ?User $user */
        $user = auth()->user();

        return $this->vue('project-home-page', 'Home', [
            'project-id' => $this->project->Id,
            'dashboard-date' => $dashboardDate,
            'testing-day-start' => $this->project->NightlyDateTime?->format('H:i e'),
            'nightly-time' => $this->project->NightlyDateTime?->format('H:i:s') ?? '00:00:00',
            'nightly-timezone' => $nightlyTimezone,
            'can-update' => $user?->can('update', $eloquentProject) ?? false,
        ]);
    }
}
