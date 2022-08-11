<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'tests/test_branchcoverage.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Project;

class DeferredSubmissionsTestCase extends BranchCoverageTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->ConfigFile = dirname(__FILE__) . '/../../../.env';
        $this->Original = file_get_contents($this->ConfigFile);

        // Make sure we start from scratch each time the test is run.
        $this->project = new Project();
        $this->projectname = 'DeferredSubmissions';
        if ($this->project->FindByName($this->projectname)) {
            remove_project_builds($this->project->Id);
            $this->project->Delete();
        }
        $this->project->Id = $this->createProject([
            'Name' => $this->projectname, 'DisplayLabels' => '1'
        ]);
    }

    public function __destruct()
    {
        file_put_contents($this->ConfigFile, $this->Original);
    }

    public function testDeferredSubmissions()
    {
        $this->deleteLog($this->logfilename);

        // Delete the existing build if it exists.
        $existing_build_row = \DB::table('build')
            ->where('projectid', '=', $this->project->Id)
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
        $this->submission($this->projectname, "$dir/Build.xml");
        $this->submission($this->projectname, "$dir/Configure.xml");
        $this->submission($this->projectname, "$dir/Test.xml");

        // Verify that files exist in the inbox directory.
        $this->assertEqual(3, count(Storage::files('inbox')));

        // Restore original database configuration.
        file_put_contents($this->ConfigFile, $this->Original);

        // Exercise the Artisan command to queue the previously submitted files.
        // This also parses them since we're currently configured for synchronous submissions.
        Artisan::call('submission:queue');

        // Verify the results.
        $build_row = \DB::table('build')
            ->where('projectid', '=', $this->project->Id)
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

    public function testDeferredUnparsedSubmission()
    {
        // Delete existing results (if any).
        $this->clearPriorResults();

        // Make sure inbox is empty.
        $files = Storage::allFiles('inbox');
        Storage::delete($files);

        // Change CDash config to point to a nonexistent database.
        file_put_contents($this->ConfigFile, "DB_DATABASE=cdash4simpletestfake\n", FILE_APPEND | LOCK_EX);

        // Submit data.
        $this->postSubmit();
        $this->putSubmit();

        // Verify that files exist in the inbox directory.
        $this->assertEqual(2, count(Storage::files('inbox')));

        // Restore original database configuration.
        file_put_contents($this->ConfigFile, $this->Original);

        // Exercise the Artisan command to queue the previously submitted files.
        // This also parses them since we're currently configured for synchronous submissions.
        Artisan::call('submission:queue');

        // Get the newly created buildid.
        $build_row = \DB::table('build')
            ->where('projectid', '=', $this->project->Id)
            ->where('name', '=', 'branch_coverage')
            ->first();
        if (!$build_row) {
            $this->fail('Could not locate branch coverage build id');
        }
        $this->buildid = $build_row->id;

        // Verify the results.
        $this->verifyResults();
    }
}
