<?php

require_once __DIR__ . '/cdash_test_case.php';

use App\Models\Test;
use CDash\Model\Project;
use Illuminate\Support\Facades\DB;

class MultipleLabelsForTestsTestCase extends KWWebTestCase
{
    private ?Project $project;

    public function __construct()
    {
        parent::__construct();
        $this->project = null;
    }

    public function __destruct()
    {
        // Delete project & build created by this test.
        if ($this->project !== null) {
            remove_project_builds($this->project->Id);
            App\Models\Project::findOrFail((int) $this->project->Id)->delete();
        }
    }

    public function testMultipleLabelsForTests(): void
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'MultipleLabelsForTests',
            'DisplayLabels' => '1',
        ]);
        $this->project->Fill();

        $this->deleteLog($this->logfilename);

        // Submit our testing data.
        $test_dir = __DIR__ . '/data/MultipleLabelsForTests/';
        $filename = "{$test_dir}/Test.xml";
        if (!$this->submission('MultipleLabelsForTests', $filename)) {
            $this->fail("Failed to submit {$filename}");
        }

        // No errors in the log.
        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        // The build exists.
        $results = DB::select('SELECT id FROM build WHERE projectid = ?', [(int) $this->project->Id]);
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
