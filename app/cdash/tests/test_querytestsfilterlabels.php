<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Database;
use CDash\Model\Project;

class QueryTestsFilterLabelsTestCase extends KWWebTestCase
{
    private $project;

    public function __construct()
    {
        parent::__construct();
        $this->project = null;
        $this->deleteLog($this->logfilename);
    }

    public function __destruct()
    {
        // Delete project & build created by this test.
        if ($this->project) {
            remove_project_builds($this->project->Id);
            $this->project->Delete();
        }
    }

    public function testQueryTestsFilterLabels()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'QueryTestsFilterLabels',
        ]);
        $this->project->Fill();

        // Submit our testing data.
        $dir = dirname(__FILE__) . '/data/QueryTestsFilterLabels';
        $files = ['Test_1.xml', 'Test_2.xml'];
        foreach ($files as $file) {
            if (!$this->submission('QueryTestsFilterLabels', "{$dir}/{$file}")) {
                $this->fail("Failed to submit $file");
            }
        }

        // No errors in the log.
        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        // Verify two builds.
        $results = \DB::select(
            DB::raw('SELECT id FROM build WHERE projectid = :projectid'),
            [':projectid' => $this->project->Id]
        );
        $this->assertTrue(2 === count($results));

        // Verify that queryTests.php only returns one test when filtering by label (not two).
        $this->get("{$this->url}/api/v1/queryTests.php?project=QueryTestsFilterLabels&date=2021-11-17&filtercount=1&showfilters=1&field1=label&compare1=63&value1=label1");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(1, count($jsonobj['builds']));
    }
}
