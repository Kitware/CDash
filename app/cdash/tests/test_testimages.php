<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Model\Project;

class TestImagesTestCase extends KWWebTestCase
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

    public function testTestImages()
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'TestImages',
        ]);
        $this->project->Fill();

        // Submit our testing data.
        $test_dir = dirname(__FILE__) . '/data/TestImages/';
        for ($i = 1; $i < 3; $i++) {
            if (!$this->submission('TestImages', "{$test_dir}/Test_{$i}.xml")) {
                $this->fail("Failed to submit Test_{$i}.xml");
            }
        }

        // Verify two separate testoutput rows.
        $results = \DB::select(
            DB::raw("
                SELECT outputid FROM build2test
                JOIN build on (build2test.buildid = build.id)
                WHERE build.projectid = :projectid"),
            [':projectid' => $this->project->Id]
        );

        $this->assertTrue(2 === count($results));
        $outputid1 = $results[0]->outputid;
        $outputid2 = $results[1]->outputid;
        $this->assertTrue($outputid1 != $outputid2);

        // Verify that these testoutputs have separate images.
        $image_results = \DB::select(
            DB::raw("
                SELECT id FROM test2image
                WHERE outputid IN (:outputid1, :outputid2)"),
            [':outputid1' => $outputid1, ':outputid2' => $outputid2]
        );
        $this->assertTrue(2 === count($image_results));
        $image_id1 = $image_results[0]->id;
        $image_id2 = $image_results[1]->id;
        $this->assertTrue($image_id1 != $image_id2);
    }
}
