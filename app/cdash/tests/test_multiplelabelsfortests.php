<?php

require_once dirname(__FILE__) . '/cdash_test_case.php';

use App\Models\Test;
use CDash\Model\Project;
use Illuminate\Support\Facades\DB;
use Tests\Traits\CreatesProjects;

class MultipleLabelsForTestsTestCase extends KWWebTestCase
{
    use CreatesProjects;

    private App\Models\Project $project;

    public function __construct()
    {
        parent::__construct();

        $this->project = $this->makePublicProject();
        $legacy_project = new Project();
        $legacy_project->Id = $this->project->id;
        $legacy_project->InitialSetup();
    }

    public function __destruct()
    {
        // Delete project & build created by this test.
        remove_project_builds($this->project->id);
        $this->project->delete();
    }

    public function testMultipleLabelsForTests()
    {
        // Submit our testing data.
        $test_dir = dirname(__FILE__) . '/data/MultipleLabelsForTests/';
        $filename = "{$test_dir}/Test.xml";
        if (!$this->submission($this->project->name, $filename)) {
            $this->fail("Failed to submit {$filename}");
        }

        // The build exists.
        $results = DB::select('SELECT id FROM build WHERE projectid = ?', [(int) $this->project->id]);
        $this->assertTrue(1 === count($results));

        // Verify that the test has multiple labels.
        $buildid = $results[0]->id;
        $buildtest = Test::where('buildid', '=', $buildid)->first();
        $this->assertTrue(3 === count($buildtest->getLabels()));

        // Verify that these labels are correctly returned by the testDetails API.
        $this->get("{$this->url}/api/v1/testDetails.php?buildtestid={$buildtest->id}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertEqual('label1, label2, label3', $jsonobj['test']['labels']);

        // Verify that these labels are correctly returned by the viewTests API.
        $this->get("{$this->url}/api/v1/viewTest.php?buildid={$buildid}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertTrue(3 === count($jsonobj['tests'][0]['labels']));
    }
}
