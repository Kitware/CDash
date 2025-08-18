<?php

require_once dirname(__FILE__) . '/cdash_test_case.php';

use App\Models\TestImage;
use CDash\Model\Project;
use Illuminate\Support\Facades\DB;

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

    public function testTestImages(): void
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

        // Verify two separate test rows.
        $results = DB::select('
            SELECT build2test.id
            FROM build2test
            JOIN build on (build2test.buildid = build.id)
            WHERE build.projectid = ?
        ', [(int) $this->project->Id]);

        $this->assertTrue(2 === count($results));
        $testid1 = $results[0]->id;
        $testid2 = $results[1]->id;
        $this->assertTrue($testid1 != $testid2);

        // Verify that these testoutputs have separate images.
        $image_results = TestImage::whereIn('testid', [$testid1, $testid2])->get();
        $this->assertTrue(2 === count($image_results));
        $image_id1 = $image_results[0]?->id;
        $image_id2 = $image_results[1]?->id;
        $this->assertTrue($image_id1 != $image_id2);
    }
}
