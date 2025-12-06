<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BuildGroup;
use App\Models\Project;
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
}
