<?php

require_once dirname(__FILE__) . '/cdash_test_case.php';

use App\Models\Build;
use CDash\Model\Project;
use Illuminate\Support\Facades\DB;
use Tests\Traits\CreatesProjects;

class LongBuildNameTestCase extends KWWebTestCase
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

    public function testLongBuildName()
    {
        // Submit our testing data.
        $test_dir = dirname(__FILE__) . '/data/LongBuildName/';
        $filename = "{$test_dir}/Configure.xml";
        if (!$this->submission($this->project->name, $filename)) {
            $this->fail("Failed to submit {$filename}");
        }

        // The build exists.
        $results = DB::select('SELECT id FROM build WHERE projectid = ?', [(int) $this->project->id]);
        $this->assertTrue(1 === count($results));

        // Its configure log was stored correctly.
        $configure = Build::findOrFail((int) $results[0]->id)->configure()->first();
        $this->assertTrue($configure !== null);
        $this->assertTrue(str_contains($configure->log, 'This is my config output'));
    }
}
