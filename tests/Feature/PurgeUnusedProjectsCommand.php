<?php

namespace Tests\Feature;

use CDash\Model\Build;
use CDash\Model\Project;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurgeUnusedProjectsCommand extends TestCase
{
    private Project $project1;
    private Project $project2;

    /**
     * Feature test for the build:remove artisan command.
     *
     * @return void
     */
    public function testAutoRemoveBuildsCommand()
    {
        // Make a project.
        $this->project1 = new Project();
        $this->project1->Name = 'DontRemoveProject';
        $this->project1->Save();
        $this->project1->InitialSetup();

        // Make a build for the project.
        $build = new Build();
        $build->Name = 'test';
        $build->ProjectId = $this->project1->Id;
        $build->SiteId = 1;
        $build->SetStamp('20090223-0115-Experimental');
        $build->StartTime = '2009-02-23 01:15:00';
        $build->EndTime = '2009-02-23 01:15:00';
        $build->SubmitTime = '2009-02-23 01:15:00';
        $build->AddBuild();

        // Make another project.
        $this->project2 = new Project();
        $this->project2->Name = 'RemoveProject';
        $this->project2->Save();
        $this->project2->InitialSetup();


        // Run the command.
        $this->artisan('projects:clean');

        // Confirm that project 2 was deleted but project 1 was not
        self::assertEquals(0, DB::select("SELECT COUNT(*) AS c FROM project WHERE name = 'RemoveProject'")[0]->c);
        self::assertEquals(1, DB::select("SELECT COUNT(*) AS c FROM project WHERE name = 'DontRemoveProject'")[0]->c);

    }

    public function tearDown() : void
    {
        if (isset($this->project1)) {
            $this->project1->Delete();
        }
        if (isset($this->project2)) {
            $this->project2->Delete();
        }

        parent::tearDown();
    }
}
