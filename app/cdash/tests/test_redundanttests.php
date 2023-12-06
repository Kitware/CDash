<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Model\Project;
use Illuminate\Support\Facades\DB;

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
            'DisplayLabels' => '1',
        ]);
        $this->project->Fill();

        // Add 'color' as a custom test measurement for this project.
        $client = $this->getGuzzleClient();
        $measurements = [];
        $measurements[] = [
            'id' => -1,
            'name' => 'color',
            'position' => 1,
        ];
        try {
            $response = $client->request('POST',
                $this->url . '/api/v1/manageMeasurements.php',
                ['json' => ['projectid' => $this->project->Id, 'measurements' => $measurements]]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $this->fail($e->getMessage());
            return false;
        }

        // Submit our testing data.
        if (!$this->submission('RedundantTests', dirname(__FILE__) . '/data/RedundantTests/Test.xml')) {
            $this->fail('Failed to submit');
        }

        // No errors in the log.
        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        // Verify one build.
        $results = DB::select('SELECT id FROM build WHERE projectid = ?', [(int) $this->project->Id]);
        $this->assertTrue(1 === count($results));
        $buildid = $results[0]->id;

        // Verify two tests.
        $results = DB::select('SELECT id FROM build2test WHERE buildid = ?', [(int) $buildid]);
        $this->assertTrue(2 === count($results));

        // Verify expected output from 'test details' API.
        $test1found = false;
        $test2found = false;
        foreach ($results as $row) {
            $this->get("{$this->url}/api/v1/testDetails.php?buildtestid={$row->id}");
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);
            if ($jsonobj['test']['output'] == "this is a test\n") {
                $test1found = true;
            }
            if ($jsonobj['test']['output'] == "this is the same test but with different output\n") {
                $test2found = true;
            }
        }
        if (!$test1found) {
            $this->fail("test #1 output not found when expected");
        }
        if (!$test2found) {
            $this->fail("test #2 output not found when expected");
        }

        // Verify expected output from 'view tests' API.
        $this->get("{$this->url}/api/v1/viewTest.php?buildid={$buildid}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(2, count($jsonobj['tests']));

        $purple_found = false;
        $orange_found = false;
        foreach ($jsonobj['tests'] as $test) {
            if ($test['measurements'][0] === 'purple') {
                $purple_found = true;
            }
            if ($test['measurements'][0] === 'orange') {
                $orange_found = true;
            }
        }
        if (!$purple_found) {
            $this->fail("purple test not found when expected");
        }
        if (!$orange_found) {
            $this->fail("orange test not found when expected");
        }
    }
}
