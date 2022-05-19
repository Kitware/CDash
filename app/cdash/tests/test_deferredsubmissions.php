<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//

require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'tests/trilinos_submission_test.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Project;

class DeferredSubmissionsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->ConfigFile = dirname(__FILE__) . '/../../../.env';
        $this->Original = file_get_contents($this->ConfigFile);
    }

    public function __destruct()
    {
        file_put_contents($this->ConfigFile, $this->Original);
    }

    public function testDeferredSubmissions()
    {
        $this->deleteLog($this->logfilename);
        // Load InsightExample project.
        $project = new Project();
        if (!$project->FindByName('InsightExample')) {
            $this->fail('InsightExample project not found');
        }

        // Delete the existing build if it exists.
        $existing_build_row = \DB::table('build')
            ->where('projectid', '=', $project->Id)
            ->where('name', '=', 'deferred_submission')
            ->first();
        if ($existing_build_row) {
            remove_build($existing_build_row->id);
        }

        // Make sure inbox is empty.
        $files = Storage::allFiles('inbox');
        Storage::delete($files);

        // Change CDash config to point to a nonexistent database.
        file_put_contents($this->ConfigFile, "DB_DATABASE=cdash4simpletestfake\n", FILE_APPEND | LOCK_EX);

        // Submit the build files.
        $dir = dirname(__FILE__) . '/data/DeferredSubmission';
        $this->submission('InsightExample', "$dir/Build.xml");
        $this->submission('InsightExample', "$dir/Configure.xml");
        $this->submission('InsightExample', "$dir/Test.xml");

        // Verify that files exist in the inbox directory.
        $this->assertEqual(3, count(Storage::files('inbox')));

        // Restore original database configuration.
        file_put_contents($this->ConfigFile, $this->Original);

        // Exercise the Artisan command to queue the previously submitted files.
        // This also parses them since we're currently configured for synchronous submissions.
        Artisan::call('submission:queue');

        // Verify the results.

        // Delete the existing build if it exists.
        $build_row = \DB::table('build')
            ->where('projectid', '=', $project->Id)
            ->where('name', '=', 'deferred_submission')
            ->first();
        if (!$build_row) {
            $this->fail('No build found after call to submission:queue');
        }

        $this->assertEqual(0, $build_row->builderrors);
        $this->assertEqual(1, $build_row->buildwarnings);
        $this->assertEqual(1, $build_row->testpassed);
        $this->assertEqual(1, $build_row->testfailed);
    }
}
