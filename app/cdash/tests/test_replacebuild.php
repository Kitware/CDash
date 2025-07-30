<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use App\Models\Project;
use App\Utils\DatabaseCleanupUtils;
use Illuminate\Support\Facades\DB;
use Tests\Traits\CreatesProjects;

require_once dirname(__FILE__) . '/cdash_test_case.php';

class ReplaceBuildTestCase extends KWWebTestCase
{
    use CreatesProjects;

    protected Project $project;

    protected $OriginalConfigSettings;

    public function __construct()
    {
        parent::__construct();
        $this->OriginalConfigSettings = '';

        $this->project = $this->makePublicProject();
        $legacy_project = new CDash\Model\Project();
        $legacy_project->Id = $this->project->id;
        $legacy_project->InitialSetup();
    }

    public function __destruct()
    {
        $this->project->delete();
    }

    public function testReplaceBuild()
    {
        $success = true;
        $error_msg = '';

        // Submit the first test file.
        $rep = dirname(__FILE__) . '/data/ReplaceBuild';
        if (!$this->submission($this->project->name, "$rep/Build_1.xml")) {
            $this->fail('failed to submit Build_1.xml');
            return 1;
        }

        // Verify details about the build that we just created.
        $row = DB::select("SELECT id, generator FROM build WHERE name='ReplaceBuild'")[0];
        $first_buildid = $row->id;
        $first_generator = $row->generator;
        if ($first_generator !== 'ctest-3.0') {
            $error_msg = "Expected 'ctest-3.0', found '$first_generator'";
            echo "$error_msg\n";
            $success = false;
        }

        // Mark this build as ready for replacement.
        if (!pdo_query("UPDATE build SET done=1 WHERE id=$first_buildid")) {
            $error_msg = 'UPDATE query returned false';
            echo "$error_msg\n";
            $success = false;
        }

        // Submit the second test file.
        if (!$this->submission($this->project->name, "$rep/Build_2.xml")) {
            $error_msg = 'Failed to submit Build_2.xml';
            echo "$error_msg\n";
            $success = false;
        }

        // Make sure the first build doesn't exist anymore.
        $query = DB::select("SELECT * FROM build WHERE id=$first_buildid");
        $num_rows = count($query);
        if ($num_rows !== 0) {
            $error_msg = "Expected 0 rows, found $num_rows";
            echo "$error_msg\n";
            $success = false;
            DatabaseCleanupUtils::removeBuild($first_buildid);
        }

        // Verify the replacement build.
        $row = DB::select("SELECT id, generator FROM build WHERE name='ReplaceBuild'")[0];
        $second_buildid = $row->id;
        $second_generator = $row->generator;
        if ($second_generator !== 'ctest-3.1') {
            $error_msg = "Expected 'ctest-3.1', found '$second_generator'";
            echo "$error_msg\n";
            $success = false;
        }

        // Delete the build that we created during this test.
        DatabaseCleanupUtils::removeBuild($second_buildid);

        if ($success) {
            $this->pass('Test passed');
            return 0;
        } else {
            $this->fail($error_msg);
            return 1;
        }
    }
}
