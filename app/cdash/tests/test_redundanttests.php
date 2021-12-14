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

        // Verify expected output from 'test details' API.
        $this->get("{$this->url}/api/v1/testDetails.php?buildtestid={$buildtestid1}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual("this is a test\n", $jsonobj['test']['output']);

        $this->get("{$this->url}/api/v1/testDetails.php?buildtestid={$buildtestid2}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual("this is the same test but with different output\n", $jsonobj['test']['output']);

        // Verify expected output from 'view tests' API.
        $this->get("{$this->url}/api/v1/viewTest.php?buildid={$buildid}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual(2, count($jsonobj['tests']));
        $this->assertEqual('purple', $jsonobj['tests'][0]['measurements'][0]);
        $this->assertEqual('orange', $jsonobj['tests'][1]['measurements'][0]);
    }
}
