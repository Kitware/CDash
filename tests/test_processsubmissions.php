<?php

require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');
require_once('cdash/pdo.php');


class ProcessSubmissionsTestCase extends KWWebTestCase
{
  var $url           = null;
  var $db            = null;
  var $projecttestid = null;

  function __construct()
    {
    parent::__construct();
    require('config.test.php');
    $this->url = $configure['urlwebsite'];
    $this->db  =& new database($db['type']);
    $this->db->setDb($db['name']);
    $this->db->setHost($db['host']);
    $this->db->setUser($db['login']);
    $this->db->setPassword($db['pwd']);
    }

  function addFakeSubmissionRecords()
    {
    // Insert fake submission records for projectid 1.
    // One old one (older than the "stuck threshold") with status=1
    // and one new one (right now) with status=0
    //
    // After processing, verify that all records for projectid 1 have
    // status>1... (that exactly zero have status 0 or 1...)
    //
    global $CDASH_SUBMISSION_PROCESSING_TIME_LIMIT;

    $old_time = gmdate(FMT_DATETIMESTD, time()-(2*$CDASH_SUBMISSION_PROCESSING_TIME_LIMIT));

    pdo_query(
      "INSERT INTO submission ".
      " (filename,projectid,status,attempts,filesize,filemd5sum,created) ".
      "VALUES ".
      " ('bogus_submission_file_1.noxml','1','1','1','999','bogus_md5sum_1','$old_time')"
    );

    pdo_query(
      "INSERT INTO submission ".
      " (filename,projectid,status,attempts,filesize,filemd5sum,created) ".
      "VALUES ".
      " ('bogus_submission_file_2.noxml','1','0','0','999','bogus_md5sum_2',UTC_TIMESTAMP())"
    );

    return 0;
    }

  function allRecordsProcessed()
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

    $c0 = pdo_get_field_value("SELECT COUNT(*) AS c FROM submission WHERE status=0", 'c', '');
    $c1 = pdo_get_field_value("SELECT COUNT(*) AS c FROM submission WHERE status=1", 'c', '');
    $c2 = pdo_get_field_value("SELECT COUNT(*) AS c FROM submission WHERE status=2", 'c', '');
    $c3 = pdo_get_field_value("SELECT COUNT(*) AS c FROM submission WHERE status=3", 'c', '');
    $c_total = pdo_get_field_value("SELECT COUNT(*) AS c FROM submission", 'c', '');

    echo "Counts of submission status values:\n";
    echo "===================================\n";
    echo "c0='$c0'\n";
    echo "c1='$c1'\n";
    echo "c2='$c2'\n";
    echo "c3='$c3'\n";
    echo "c_total='$c_total'\n";

    if ($c0 == 0 && $c1 == 0)
      {
      return 1;
      }

    return 0;
    }


  function testProcessSubmissionsTest()
    {
    $this->login();
    $content = $this->get($this->url."/cdash/processsubmissions.php");
    if(strpos($content, "projectid/argv[1] should be a number") === false)
      {
      $this->fail("'projectid/argv[1] should be a number' not found when expected");
      return 1;
      }
    $content = $this->get($this->url."/cdash/processsubmissions.php?projectid=1");
    if(strpos($content, "Done with ProcessSubmissions") === false)
      {
      $this->fail("'Done with ProcessSubmissions' not found when expected");
      return 1;
      }

    // Simulate the processsubmissions.php "been processing for a long time"
    // issue. (Add records that are in the "processing" state, but appear to
    // be "old"... And records *after* that in the "queued" state.)
    // Then validate that processsubmissions properly processes the old record
    // *and* the queued records.
    //
    $this->addFakeSubmissionRecords();

    $content = $this->get($this->url."/cdash/processsubmissions.php?projectid=1");
    if(strpos($content, "Done with ProcessSubmissions") === false)
      {
      $this->fail("'Done with ProcessSubmissions' not found when expected");
      return 1;
      }

    if (!$this->allRecordsProcessed())
      {
      $this->fail("some records still not processed after calling processsubmissions.php");
      return 1;
      }

    $this->pass("Passed");
    return 0;
    }
}
?>
