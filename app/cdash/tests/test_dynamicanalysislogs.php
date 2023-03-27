<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Database;
use CDash\Model\Project;
use CDash\Model\DynamicAnalysis;

class DynamicAnalysisLogsTestCase extends KWWebTestCase
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

    public function testDynamicAnalysisLogs()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'DynamicAnalysisLogs',
        ]);
        $this->project->Fill();

        // Submit our testing data.
        $test_dir = dirname(__FILE__) . '/data/DynamicAnalysisLogs/';
        $files = ['Build.xml', 'Configure.xml', 'Test.xml', 'DynamicAnalysis.xml'];
        foreach ($files as $file) {
            if (!$this->submission('DynamicAnalysisLogs', "{$test_dir}/{$file}")) {
                $this->fail("Failed to submit {$file}");
            }
        }

        // Verify full log file was recorded.
        $results = \DB::select(
            DB::raw("
                SELECT dynamicanalysis.id FROM dynamicanalysis
                JOIN build on (dynamicanalysis.buildid = build.id)
                WHERE build.projectid = :projectid"),
            [':projectid' => $this->project->Id]
        );
        $this->assertTrue(1 === count($results));
        $id = $results[0]->id;
        $DA = new DynamicAnalysis();
        $DA->Id = $id;
        if (!$DA->Fill()) {
            $this->fail("Failed to fill dynamic analysis object");
        }

        if (strpos($DA->Log, 'Memcheck, a memory error detector') === false) {
            $this->fail("Failed to find beginning of log");
        }

        if (strpos($DA->Log, 'Goodbye world') === false) {
            $this->fail("Failed to find end of log");
        }
    }
}
