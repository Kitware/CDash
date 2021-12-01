<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Database;
use CDash\Model\Project;

class RedundantTestsTestCase extends KWWebTestCase
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

    public function testRedundantTests()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'RedundantTests',
        ]);
        $this->project->Fill();

        // Submit our testing data.
        if (!$this->submission('RedundantTests', dirname(__FILE__) . '/data/RedundantTests/Test.xml')) {
            $this->fail('Failed to submit');
        }

        // No errors in the log.
        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        // Verify one build.
        $results = \DB::select(
            DB::raw('SELECT id FROM build WHERE projectid = :projectid'),
            [':projectid' => $this->project->Id]
        );
        $this->assertTrue(1 === count($results));
        $buildid = $results[0]->id;

        // Verify two tests.
        $results = \DB::select(
            DB::raw('SELECT id FROM build2test WHERE buildid = :buildid'),
            [':buildid' => $buildid]
        );
        $this->assertTrue(2 === count($results));

        $buildtestid1 = $results[0]->id;
        $buildtestid2 = $results[1]->id;

        // Verify expected output from API.
        $this->get("{$this->url}/api/v1/testDetails.php?buildtestid={$buildtestid1}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual("this is a test\n", $jsonobj['test']['output']);

        $this->get("{$this->url}/api/v1/testDetails.php?buildtestid={$buildtestid2}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual("this is the same test but with different output\n", $jsonobj['test']['output']);
    }
}
