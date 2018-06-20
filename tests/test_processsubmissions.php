<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/pdo.php';

class ProcessSubmissionsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /* TODO: rewrite this test
    public function addFakeSubmissionRecords($projectid)
    {
        // Insert fake submission records for the given projectid.
        // One old one (older than the "stuck threshold") with status=1
        // and one new one (right now) with status=0
        //
        // After processing, verify that all records for projectid 1 have
        // status>1... (that exactly zero have status 0 or 1...)
        //
        global $CDASH_SUBMISSION_PROCESSING_TIME_LIMIT;

        $old_time = gmdate(FMT_DATETIMESTD, time() - (2 * $CDASH_SUBMISSION_PROCESSING_TIME_LIMIT));
        $now_utc = gmdate(FMT_DATETIMESTD);
        $n = 3;

        $i = 0;
        while ($i < $n) {
            pdo_query(
                'INSERT INTO submission ' .
                ' (filename,projectid,status,attempts,filesize,filemd5sum,created,started) ' .
                'VALUES ' .
                " ('bogus_submission_file_1.noxml','$projectid','1','1','999','bogus_md5sum_1','$old_time','$old_time')"
            );

            ++$i;
        }

        $i = 0;
        while ($i < $n) {
            pdo_query(
                'INSERT INTO submission ' .
                ' (filename,projectid,status,attempts,filesize,filemd5sum,created) ' .
                'VALUES ' .
                " ('bogus_submission_file_2.noxml','$projectid','0','0','999','bogus_md5sum_2','$now_utc')"
            );

            ++$i;
        }
        return 0;
    }

    public function addFakeStaleProcessingLock($projectid)
    {
        // Setup fake "stale" submissionprocessor record for the given projectid.
        //
        // This function assumes the record already exists for projectid. It does
        // because prior to calling this method, some calls to
        // processsubmissions.php for the projectid have already been made.
        // If not, this function should check and INSERT instead...
        //
        global $CDASH_SUBMISSION_PROCESSING_TIME_LIMIT;

        $old_time = gmdate(FMT_DATETIMESTD, time() - (2 * $CDASH_SUBMISSION_PROCESSING_TIME_LIMIT));

        pdo_query(
            'UPDATE submissionprocessor ' .
            "SET pid='1', lastupdated='$old_time', locked='$old_time' " .
            "WHERE projectid='$projectid'"
        );
    }

    public function allRecordsProcessed($projectid)
    {
        // The status field in the submission table may have the value 0, 1, 2 or 3.
        // 0 means queued, but not yet (or no longer) processing.
        // 1 means processing.
        // 2 means done processing, did call do_submit.
        // 3 means done processing, did not call do_submit.
        //
        // This function returns 1 if there are exactly 0 records in the submission
        // table with status=0 or 1.
        //
        // This function returns 0 if any record in the table has status=0 or 1.

        $c0 = pdo_get_field_value("SELECT COUNT(*) AS c FROM submission WHERE status=0 AND projectid='$projectid'", 'c', '');
        $c1 = pdo_get_field_value("SELECT COUNT(*) AS c FROM submission WHERE status=1 AND projectid='$projectid'", 'c', '');
        $c2 = pdo_get_field_value("SELECT COUNT(*) AS c FROM submission WHERE status=2 AND projectid='$projectid'", 'c', '');
        $c3 = pdo_get_field_value("SELECT COUNT(*) AS c FROM submission WHERE status=3 AND projectid='$projectid'", 'c', '');
        $c_total = pdo_get_field_value("SELECT COUNT(*) AS c FROM submission WHERE projectid='$projectid'", 'c', '');

        echo "Counts of submission status values:\n";
        echo "===================================\n";
        echo "  (for projectid='$projectid')\n";
        echo "c0='$c0'\n";
        echo "c1='$c1'\n";
        echo "c2='$c2'\n";
        echo "c3='$c3'\n";
        echo "c_total='$c_total'\n";

        if ($c0 == 0 && $c1 == 0) {
            return 1;
        }
        return 0;
    }

    public function launchViaCurl($path, $timeout)
    {
        $request = $this->url . $path;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_exec($ch);
        curl_close($ch);
    }

    public function launchViaCommandLine($projectid)
    {
        global $cdashpath;
        global $PHP_EXE;
        $cmd = "\"$PHP_EXE\" \"$cdashpath/public/ajax/processsubmissions.php\" $projectid --force";
        echo "Running command line:\n";
        echo "  cmd='${cmd}'\n";
        $result = system($cmd);
        echo "\n";
        echo "  result='$result'\n";
        echo "Done with command line\n";
    }

    public function testProcessSubmissionsTest()
    {
        $this->login();

        echo "CTEST_FULL_OUTPUT\n";
        echo "this->logfilename='$this->logfilename'\n";
        echo "this->url='$this->url'\n";

        $content = $this->get($this->url . '/ajax/processsubmissions.php');
        if (strpos($content, 'projectid/argv[1] should be a number') === false) {
            $this->fail("'projectid/argv[1] should be a number' not found when expected");
            echo "content (1):\n$content\n";
            return 1;
        }

        $content = $this->get($this->url . '/ajax/processsubmissions.php?projectid=1');
        if (strpos($content, 'Done with ProcessSubmissions') === false) {
            $this->fail("'Done with ProcessSubmissions' not found when expected");
            echo "content (2):\n$content\n";
            return 1;
        }

        global $CDASH_LOG_FILE;
        if ($CDASH_LOG_FILE !== false) {
            echo 'log file perms: [';
            echo substr(sprintf('%o', fileperms($this->logfilename)), -4) . "]\n";
        }

        // Simulate the processsubmissions.php "been processing for a long time"
        // issue. (Add records that are in the "processing" state, but appear to
        // be "old"... And records *after* that in the "queued" state.)
        // Then validate that processsubmissions properly processes the old record
        // *and* the queued records.
        //
        $this->addFakeSubmissionRecords('1');

        // Launch the first instance of the processor process via curl and tell
        // it to take a long time by sleeping each time through its loop.
        // (With 6 fake records just added, it'll sleep for about 6 seconds,
        // 1 second for each time through its loop...)
        //
        $this->launchViaCurl('/ajax/processsubmissions.php?projectid=1&sleep_in_loop=1', 1);

        // Sleep for 2 seconds, and then try to process submissions synchronously
        // and simultaneously... (This one should go through the "can't acquire
        // lock" code path.)
        //
        echo "sleep(2)\n";
        sleep(2);

        $content = $this->get($this->url . '/ajax/processsubmissions.php?projectid=1');
        if (strpos($content, 'Another process is already processing') === false) {
            $this->fail("'Another process is already processing' not found when expected");
            echo "content (3):\n$content\n";
            return 1;
        }

        // Now... sleep for 10 seconds before checking to see if all processing
        // is done:
        //
        echo "sleep(10)\n";
        sleep(10);

        if (!$this->allRecordsProcessed('1')) {
            // projectid 1 is tested in this test...

            $rows = pdo_all_rows_query('SELECT * FROM submission WHERE status<2');
            echo print_r($rows, true) . "\n";

            $this->fail('some records still not processed after call 1 processsubmissions.php');
            return 1;
        }

        // Done, right? Not quite.
        // Now add some more fake submissions, and add a fake, stale processing
        // lock, such that the processing code has to go through the "acquire
        // lock by assuming existing lock is dead, so steal it" chunk of code.
        //
        $this->addFakeSubmissionRecords('1');
        $this->addFakeStaleProcessingLock('1');
        $content = $this->get($this->url . '/ajax/processsubmissions.php?projectid=1');

        if (!$this->allRecordsProcessed('1')) {
            // projectid 1 is tested in this test...

            $rows = pdo_all_rows_query('SELECT * FROM submission WHERE status<2');
            echo print_r($rows, true) . "\n";

            $this->fail('some records still not processed after call 2 processsubmissions.php');
            echo "content (4):\n$content\n";
            return 1;
        }

        // Finally, execute the processsubmissions.php script by php command line
        // to get coverage of the chunk of code that processes command line args.
        //
        $this->addFakeSubmissionRecords('1');
        $this->addFakeStaleProcessingLock('1');
        $this->launchViaCommandLine(1);

        if (!$this->allRecordsProcessed('1')) {
            // projectid 1 is tested in this test...

            $rows = pdo_all_rows_query('SELECT * FROM submission WHERE status<2');
            echo print_r($rows, true) . "\n";

            $this->fail('some records still not processed after call 3 processsubmissions.php');
            return 1;
        }

        // Actually, with this test, we expect some errors to be logged in the
        // cdash.log file, so do not do this check:
        //
        //if(!$this->checkLog($this->logfilename))
        //  {
        //  return 1;
        //  }

        $this->pass('Passed');
        return 0;
    }
    */
}
