<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Model\Project;
use Illuminate\Support\Facades\DB;

class NamedMeasurementsTestCase extends KWWebTestCase
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

    public function testNamedMeasurements()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'NamedMeasurements',
        ]);
        $this->project->Fill();

        // Submit our testing data.
        $test_dir = dirname(__FILE__) . '/data/NamedMeasurements/';
        $files = ['Test_1.xml', 'Test_2.xml'];
        foreach ($files as $file) {
            if (!$this->submission('NamedMeasurements', "{$test_dir}/{$file}")) {
                $this->fail("Failed to submit {$file}");
            }
        }

        // Verify both measurements were inserted separately.
        $results = DB::select("
            SELECT testmeasurement.value
            FROM testmeasurement
            JOIN testoutput ON (testmeasurement.outputid = testoutput.id)
            JOIN build2test ON (testoutput.id = build2test.outputid)
            JOIN build ON (build2test.buildid = build.id)
            WHERE build.projectid = :projectid
            AND testmeasurement.name = 'archive directory'
        ", [(int) $this->project->Id]);

        $this->assertTrue(2 === count($results));
        $found_link1 = false;
        $found_link2 = false;
        foreach ($results as $result) {
            if ($result->value == 'https://example.com/link1.txt') {
                $found_link1 = true;
            }
            if ($result->value == 'https://example.com/link2.txt') {
                $found_link2 = true;
            }
        }
        $this->assertTrue($found_link1);
        $this->assertTrue($found_link2);
    }
}
