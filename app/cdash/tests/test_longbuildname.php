<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use App\Models\Build;
use CDash\Model\Project;
use Illuminate\Support\Facades\DB;

class LongBuildNameTestCase extends KWWebTestCase
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

    public function testLongBuildName()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'LongBuildName',
        ]);
        $this->project->Fill();

        $this->deleteLog($this->logfilename);

        // Submit our testing data.
        $test_dir = dirname(__FILE__) . '/data/LongBuildName/';
        $filename = "{$test_dir}/Configure.xml";
        if (!$this->submission('LongBuildName', $filename)) {
            $this->fail("Failed to submit {$filename}");
        }

        // No errors in the log.
        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        // The build exists.
        $results = DB::select("SELECT id FROM build WHERE projectid = ?", [(int) $this->project->Id]);
        $this->assertTrue(1 === count($results));

        // Its configure log was stored correctly.
        $configure = Build::findOrFail((int) $results[0]->id)->configure()->first();
        $this->assertTrue($configure !== null);
        $this->assertTrue(str_contains($configure->log, 'This is my config output'));
    }
}
