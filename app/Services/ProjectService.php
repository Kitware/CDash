<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BuildGroup;
use App\Models\Project;
use App\Models\SubProjectGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProjectService extends AbstractService
{
    /**
     * Creates a project record and initializes Nightly, Continuous, and Experimental default build groups.
     *
     * @param array<string,mixed> $attributes
     */
    public static function create(array $attributes): Project
    {
        $project = Project::create($attributes);

        self::initializeBuildGroups($project);

        return $project;
    }

    /** This method is meant to be temporary, eventually only being called in create() */
    public static function initializeBuildGroups(Project $project): void
    {
        $common_defaults = [
            'starttime' => Carbon::create(1980),
            'endtime' => Carbon::create(1980),
            'type' => 'Daily',
            'includesubprojectotal' => 1,
            'emailcommitters' => 0,
        ];

        /** @var BuildGroup $nightly */
        $nightly = $project->buildgroups()->create([
            'name' => 'Nightly',
            'description' => 'Nightly builds',
            'summaryemail' => 0,
        ] + $common_defaults);

        $nightly->positions()->create([
            'position' => 1,
            'starttime' => Carbon::create(1980),
            'endtime' => Carbon::create(1980),
        ]);

        // Set up overview page to initially contain just the "Nightly" group.
        DB::table('overview_components')->insert([
            'projectid' => $project->id,
            'buildgroupid' => $nightly->id,
            'position' => 1,
            'type' => 'build',
        ]);

        $continuous = $project->buildgroups()->create([
            'name' => 'Continuous',
            'description' => 'Continuous builds',
            'summaryemail' => 0,
        ] + $common_defaults);

        $continuous->positions()->create([
            'position' => 2,
            'starttime' => Carbon::create(1980),
            'endtime' => Carbon::create(1980),
        ]);

        $experimental = $project->buildgroups()->create([
            'name' => 'Experimental',
            'description' => 'Experimental builds',
            // default to "No Email" for the Experimental group
            'summaryemail' => 2,
        ] + $common_defaults);

        $experimental->positions()->create([
            'position' => 3,
            'starttime' => Carbon::create(1980),
            'endtime' => Carbon::create(1980),
        ]);
    }

    /**
     * @deprecated 12/06/2025  Use Eloquent relationships for all new code
     *
     * @return \CDash\Model\BuildGroup[]
     */
    public static function getBuildGroups(int $projectid): array
    {
        $eloquent_buildgroups = Project::findOrFail($projectid)
            ->buildgroups()
            ->where('endtime', Carbon::create(1980))
            ->get();

        $buildgroups = [];
        /** @var BuildGroup $model */
        foreach ($eloquent_buildgroups as $model) {
            $buildgroup = new \CDash\Model\BuildGroup();
            $buildgroup->SetId($model->id);
            $buildgroup->SetName($model->name);
            $buildgroups[] = $buildgroup;
        }
        return $buildgroups;
    }

    /**
     * @deprecated 12/06/2025  Use Eloquent relationships for all new code
     */
    public static function getLastStartTimestamp(int $projectid): string|false
    {
        if (!config('cdash.show_last_submission')) {
            return false;
        }

        $starttime = Project::findOrFail($projectid)
            ->builds()
            ->max('starttime');

        if ($starttime === null) {
            return false;
        }

        return date(FMT_DATETIMESTD, strtotime($starttime . 'UTC'));
    }

    /**
     * Get the number of subprojects, optionally as of the specified date
     *
     * @deprecated 12/06/2025  Use Eloquent relationships for all new code
     */
    public static function getNumberOfSubProjects(int $projectid, $date = null): int
    {
        if ($date !== null) {
            $date = Carbon::parse($date);
        }

        return Project::findOrFail($projectid)
            ->subprojects($date)
            ->count();
    }

    /**
     * Return the list of subproject groups that belong to this project.
     *
     * @return array<\CDash\Model\SubProjectGroup>
     *
     * @deprecated 12/06/2025  Use Eloquent relationships for all new code
     */
    public static function getSubProjectGroups(int $projectid): array
    {
        $groups = Project::findOrFail($projectid)
            ->subProjectGroups()
            ->where('endtime', '1980-01-01 00:00:00')
            ->get();

        $subProjectGroups = [];
        /** @var SubProjectGroup $group */
        foreach ($groups as $group) {
            $subProjectGroup = new \CDash\Model\SubProjectGroup();
            // SetId automatically loads the rest of the group's data.
            $subProjectGroup->SetId($group->id);
            $subProjectGroups[] = $subProjectGroup;
        }
        return $subProjectGroups;
    }
}
