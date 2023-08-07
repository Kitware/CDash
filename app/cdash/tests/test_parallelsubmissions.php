<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//

require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'tests/trilinos_submission_test.php';

require_once 'include/pdo.php';

use CDash\Model\Project;

class ParallelSubmissionsTestCase extends TrilinosSubmissionTestCase
{
    protected $ConfigFile;
    protected $Original;

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

    public function testParallelSubmissions()
    {
        $this->deleteLog($this->logfilename);
        \DB::table('successful_jobs')->delete();

        // Load Trilinos project.
        $project = new Project();
        if (!$project->FindByName('Trilinos')) {
            $this->fail('Trilinos project not found');
        }

        // Delete the existing Trilinos build if it exists.
        $trilinos_build_row = \DB::table('build')
            ->where('parentid', '=', '-1')
            ->where('projectid', '=', $project->Id)
            ->where('name', '=', 'Windows_NT-MSVC10-SERIAL_DEBUG_DEV')
            ->whereBetween('starttime', ['2011-07-22 00:00:00', '2011-07-22 23:59:59'])
            ->first();
        if ($trilinos_build_row) {
            remove_build($trilinos_build_row->id);
        }

        // Change CDash config to queue submissions in the database.
        file_put_contents($this->ConfigFile, "QUEUE_CONNECTION=database\n", FILE_APPEND | LOCK_EX);

        // Re-submit the Trilinos build.
        $begin = time();
        $this->submitFiles('ActualTrilinosSubmission', true);
        echo 'Submission took ' . (time() - $begin) . " seconds.\n";

        // Verify some queued jobs.
        $num_jobs = \DB::table('jobs')->count();
        $this->assertEqual(147, $num_jobs);

        // Start 4 queue workers.
        chdir(dirname(__FILE__) . '/../../../');
        foreach (range(0, 3) as $i) {
            exec('php artisan queue:work --stop-when-empty > /dev/null 2>&1 &');
        }

        // Wait for processing to complete.
        $begin = time();
        while ($num_jobs > 0) {
            $num_jobs = \DB::table('jobs')->count();
            usleep(5000);
            if (time() - $begin > 120) {
                $this->fail("Processing took longer than 120 seconds.\n");
                break;
            }
        }
        echo 'Processing took ' . (time() - $begin) . " seconds.\n";

        // Verify number of successful jobs.
        $num_jobs = \DB::table('successful_jobs')->count();
        $this->assertEqual(147, $num_jobs);

        // Verify the results.
        $this->verifyResults();
    }
}
