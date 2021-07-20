<?php

namespace Tests\Feature;

use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AutoRemoveBuildsCommand extends TestCase
{
    /**
     * Feature test for the build:remove artisan command.
     *
     * @return void
     */
    public function testAutoRemoveBuildsCommand()
    {
        // Make a project.
        $this->project = new Project();
        $this->project->Name = 'AutoRemoveProject';
        $this->project->AutoremoveTimeframe = 45;
        $this->project->Save();
        $this->project->InitialSetup();

        // Make an old build for the project.
        $build = new Build();
        $build->Name = 'remove me';
        $build->ProjectId = $this->project->Id;
        $build->SiteId = 1;
        $build->SetStamp('20090223-0115-Experimental');
        $build->StartTime = '2009-02-23 01:15:00';
        $build->Endime = '2009-02-23 01:15:00';
        $build->SubmitTime = '2009-02-23 01:15:00';
        $build->AddBuild();

        // Make a new build for the project.
        $datetime = new \DateTime('now', new \DateTimeZone('UTC'));
        $buildstamp = $datetime->format('Ymd-His') . '-Experimental';
        $db_datetime_str = $datetime->format('Y-m-d H:i:s');

        $build = new Build();
        $build->Name = 'remove me later';
        $build->ProjectId = $this->project->Id;
        $build->SiteId = 1;
        $build->SetStamp($buildstamp);
        $build->StartTime = $db_datetime_str;
        $build->Endime = $db_datetime_str;
        $build->SubmitTime = $db_datetime_str;
        $build->AddBuild();

        // Confirm that the project has two builds.
        $db = new Database();
        $stmt = $db->prepare('SELECT COUNT(1) FROM build WHERE projectid = ?');
        $db->execute($stmt, [$this->project->Id]);
        $this->assertEquals(2, $stmt->fetchColumn());

        // Run the command.
        $this->artisan('build:remove', ['project' => 'AutoRemoveProject']);

        // Confirm that the project only has one build now.
        $db->execute($stmt, [$this->project->Id]);
        $this->assertEquals(1, $stmt->fetchColumn());

        // Run the command again with the '--all-builds' option.
        $this->artisan('build:remove', ['project' => 'AutoRemoveProject', '--all-builds' => 1]);

        // Confirm that the project has no builds.
        $db->execute($stmt, [$this->project->Id]);
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function tearDown() : void
    {
        if ($this->project) {
            $this->project->Delete();
        }
    }
}
