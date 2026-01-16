<?php

namespace Tests\Feature;

use CDash\Model\Build;
use CDash\Model\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class PurgeUnusedProjectsCommand extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private \App\Models\Project $project1;
    private \App\Models\Project $project2;

    /**
     * Feature test for the build:remove artisan command.
     */
    public function testAutoRemoveBuildsCommand(): void
    {
        // Make a project.
        $this->project1 = $this->makePublicProject('DontRemoveProject');

        // Make a build for the project.
        $build = new Build();
        $build->Name = 'test';
        $build->ProjectId = $this->project1->id;
        $build->SiteId = 1;
        $build->SetStamp('20090223-0115-Experimental');
        $build->StartTime = '2009-02-23 01:15:00';
        $build->EndTime = '2009-02-23 01:15:00';
        $build->SubmitTime = '2009-02-23 01:15:00';
        $build->AddBuild();

        // Make another project.
        $this->project2 = $this->makePublicProject('RemoveProject');

        // Run the command.
        $this->artisan('projects:clean');

        // Confirm that project 2 was deleted but project 1 was not
        self::assertEquals(0, DB::select("SELECT COUNT(*) AS c FROM project WHERE name = 'RemoveProject'")[0]->c);
        self::assertEquals(1, DB::select("SELECT COUNT(*) AS c FROM project WHERE name = 'DontRemoveProject'")[0]->c);
    }

    public function tearDown(): void
    {
        if (isset($this->project1)) {
            $this->project1->delete();
        }
        if (isset($this->project2)) {
            $this->project2->delete();
        }

        parent::tearDown();
    }
}
