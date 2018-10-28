<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'tests/trilinos_submission_test.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class ParallelSubmissionsTestCase extends TrilinosSubmissionTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->DisableProcessingConfig =
            '$CDASH_DO_NOT_PROCESS_SUBMISSIONS = true;';
        $this->ParallelProcessingConfig = '$CDASH_ASYNC_WORKERS = 10;';
    }

    /* TODO: REWRITE THIS TEST
    public function testParallelSubmissions()
    {
        // Delete the existing Trilinos build.
        $row = pdo_single_row_query(
            "SELECT id FROM build
                WHERE parentid=-1 AND
                projectid=(SELECT id FROM project WHERE name='Trilinos') AND
                name='Windows_NT-MSVC10-SERIAL_DEBUG_DEV' AND
                starttime BETWEEN '2011-07-22 00:00:00' AND '2011-07-22 23:59:59'");
        remove_build($row['id']);

        // Disable submission processing so that the queue will accumulate.
        $this->addLineToConfig($this->DisableProcessingConfig);

        // Re-submit the Trilinos build.
        $begin = time();
        $this->submitFiles('ActualTrilinosSubmission', true);
        echo 'Submission took ' . (time() - $begin) . " seconds.\n";

        // Re-enable submission processing and enable parallel processing
        $this->removeLineFromConfig($this->DisableProcessingConfig);
        $this->addLineToConfig($this->ParallelProcessingConfig);

        // Submit another file to Trilinos to start the processing loop.
        $file = dirname(__FILE__) . '/data/SubProjectNextPrevious/Build_1.xml';
        $this->submission('Trilinos', $file);

        // Wait for processing to complete.
        $todo = 999;
        $begin = time();
        while ($todo > 0) {
            $row = pdo_single_row_query('SELECT count(1) AS todo
                    FROM submission WHERE status=0 OR status=1');
            $todo = $row['todo'];
            sleep(1);
            if (time() - $begin > 120) {
                $this->fail("Processing took longer than 120 seconds.\n");
                break;
            }
        }
        echo 'Processing took ' . (time() - $begin) . " seconds.\n";

        // Verify the results.
        $this->verifyResults();

        // Clean up by reverting our config settings and deleting the
        // extra build that we created to trigger the processing.
        $this->removeLineFromConfig($this->ParallelProcessingConfig);
        $row = pdo_single_row_query(
            "SELECT build.id FROM build
                WHERE build.parentid=-1 AND
                projectid=(SELECT id FROM project WHERE name='Trilinos') AND
                stamp='20110723-1515-Experimental'");
        remove_build($row['id']);
    }
    */
}
