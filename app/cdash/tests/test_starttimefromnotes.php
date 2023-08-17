<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Model\Build;
use CDash\Model\Project;

class StartTimeFromNotesTestCase extends KWWebTestCase
{
    private $project;

    public function __construct()
    {
        parent::__construct();
        $this->project = null;
    }

    public function __destruct()
    {
        // Delete project & build created by this test.
        if ($this->project) {
            remove_project_builds($this->project->Id);
            $this->project->Delete();
        }
    }

    public function testStartTimeFromNotes()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'StartTimeFromNotes',
            'NightlyTime' => '18:00:00 America/Denver',
        ]);
        $this->project->Fill();

        // Submit our testing data.
        $file = dirname(__FILE__) . '/data/StartTimeFromNotes/Notes.xml';
        if (!$this->submission('StartTimeFromNotes', $file)) {
            $this->fail("Failed to submit {$file}");
        }

        // No errors in the log.
        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        // The build exists.
        $results = \DB::select(
            DB::raw('SELECT id FROM build WHERE projectid = :projectid'),
            [':projectid' => $this->project->Id]
        );
        $this->assertTrue(1 === count($results));

        // Verify start time & testing day.
        $build = new Build();
        $build->Id = $results[0]->id;
        $build->FillFromId($build->Id);
        $this->assertEqual('2021-09-16', $build->GetDate());
        $this->assertEqual('2021-09-16 19:19:46', $build->StartTime);

        // Verify note was stored successfully.
        $results = \DB::select(
            DB::raw("
                SELECT note.name, note.text FROM note
                JOIN build2note ON (note.id = build2note.noteid)
                JOIN build ON (build.id = build2note.buildid)
                WHERE build.id = :buildid"),
            [':buildid' => $build->Id]
        );
        $this->assertTrue(1 === count($results));
        $this->assertEqual("my very own note", $results[0]->name);
        $this->assertEqual("this is\nmy note\n", $results[0]->text);
    }
}
