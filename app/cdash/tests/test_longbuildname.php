<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Database;
use CDash\Model\BuildConfigure;
use CDash\Model\Project;

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
        if (!$this->submission('LongBuildName', "{$test_dir}/Configure.xml")) {
            $this->fail("Failed to submit {$file}");
        }

        // No errors in the log.
        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        // The build exists.
        $results = \DB::select(
            DB::raw("SELECT id FROM build WHERE projectid = :projectid"),
            [':projectid' => $this->project->Id]
        );
        $this->assertTrue(1 === count($results));

        // Its configure log was stored correctly.
        $configure = new BuildConfigure();
        $configure->BuildId = $results[0]->id;
        $log = $configure->GetConfigureForBuild()['log'];
        $this->assertTrue(strpos($log, 'This is my config output') !== false);
    }
}
