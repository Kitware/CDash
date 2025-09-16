<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PruneBuilds;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

/**
 * This test specifically tests the conditions under which old builds are removed.  A separate test
 * is responsible for ensuring that deleting a build deletes data from all the relevant tables.
 */
class PruneBuildsTest extends TestCase
{
    use CreatesProjects;

    protected Project $project;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    public function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    public function testDeletesBuildWhenBeyondAutoRemoveTimeframe(): void
    {
        config()->set('cdash.autoremove_builds', true);

        $this->project->autoremovetimeframe = 4;
        $this->project->save();

        $build_to_delete = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'starttime' => Carbon::now()->subDays(5),
        ]);

        $build_to_keep = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'starttime' => Carbon::now()->subDays(3),
        ]);

        self::assertModelExists($build_to_delete);
        self::assertModelExists($build_to_keep);
        PruneBuilds::dispatch();
        self::assertModelMissing($build_to_delete);
        self::assertModelExists($build_to_keep);
    }

    public function testDoesNotDeleteBuildsWhenAutoRemoveBuildsTurnedOff(): void
    {
        config()->set('cdash.autoremove_builds', false);

        $this->project->autoremovetimeframe = 4;
        $this->project->save();

        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'starttime' => Carbon::now()->subDays(5),
        ]);

        self::assertModelExists($build);
        PruneBuilds::dispatch();
        self::assertModelExists($build);
    }

    public function testDeletesBuildWhenBeyondBuildGroupTimeframe(): void
    {
        config()->set('cdash.autoremove_builds', true);

        $this->project->autoremovetimeframe = 10;
        $this->project->save();

        $build_to_delete = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'starttime' => Carbon::now()->subDays(7),
        ]);

        $build_to_keep = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'starttime' => Carbon::now()->subDays(3),
        ]);

        $buildgroup = $this->project->buildgroups()->create([
            'description' => Str::uuid()->toString(),
            'autoremovetimeframe' => 4,
        ]);
        $buildgroup->builds()->attach($build_to_delete);
        $buildgroup->builds()->attach($build_to_keep);

        self::assertModelExists($build_to_delete);
        self::assertModelExists($build_to_keep);
        PruneBuilds::dispatch();
        self::assertModelMissing($build_to_delete);
        self::assertModelExists($build_to_keep);
    }
}
