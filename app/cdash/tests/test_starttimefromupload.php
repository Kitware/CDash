<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Model\Build;
use CDash\Model\Project;
use Illuminate\Support\Facades\DB;

class StartTimeFromUploadTestCase extends KWWebTestCase
{
    private Project $project;

    public function __construct()
    {
        parent::__construct();
        $this->project = new Project();
    }

    public function __destruct()
    {
        // Delete project & build created by this test.
        remove_project_builds($this->project->Id);
        $this->project->Delete();
    }

    public function testStartTimeFromUpload() : void
    {
        $this->deleteLog($this->logfilename);

        // Create test project.
        $this->login();
        $this->project->Id = $this->createProject([
            'Name' => 'StartTimeFromUpload',
            'NightlyTime' => '18:00:00 America/Denver',
        ]);
        $this->project->Fill();

        // Submit our testing data.
        $file = dirname(__FILE__) . '/data/StartTimeFromUpload/Upload.xml';
        if (!$this->submission('StartTimeFromUpload', $file)) {
            $this->fail("Failed to submit {$file}");
        }

        // No errors in the log.
        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        // Verify start time & testing day.
        $build_row = \App\Models\Project::findOrFail((int) $this->project->Id)->builds()->firstOrFail();
        $build = new Build();
        $build->Id = $build_row->id;
        $build->FillFromId($build->Id);
        $this->assertEqual('2024-02-27', $build->GetDate());
        $this->assertEqual('2024-02-27 18:50:50', $build->StartTime);

        // Verify upload record was created successfully.
        $results = DB::select('
            SELECT fileid
            FROM build2uploadfile
            WHERE build2uploadfile.buildid = ?
        ', [(int) $build->Id]);
        $this->assertTrue(1 === count($results));
    }
}
