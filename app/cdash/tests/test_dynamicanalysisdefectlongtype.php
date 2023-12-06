<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Model\Project;
use Illuminate\Support\Facades\DB;

class DynamicAnalysisDefectLongTypeTestCase extends KWWebTestCase
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

    public function testDynamicAnalysisDefectLongType()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'DynamicAnalysisDefectLongType',
        ]);
        $this->project->Fill();

        // Submit our testing data.
        $file = dirname(__FILE__) . '/data/DynamicAnalysisDefectLongType/DynamicAnalysis.xml';
        if (!$this->submission('DynamicAnalysisDefectLongType', $file)) {
            $this->fail("Failed to submit {$file}");
        }

        // Verify type was properly recorded.
        $results = DB::select("
            SELECT dynamicanalysisdefect.type
            FROM dynamicanalysisdefect
            JOIN dynamicanalysis ON (dynamicanalysisdefect.dynamicanalysisid = dynamicanalysis.id)
            JOIN build on (dynamicanalysis.buildid = build.id)
            WHERE build.projectid = ?
        ", [(int) $this->project->Id]);
        $this->assertTrue(1 === count($results));
        $expected = "member call on address 0x7f9ce2a84da8 which does not point to an object of type 'error_category'";
        $this->assertEqual($expected, $results[0]->type);
    }
}
