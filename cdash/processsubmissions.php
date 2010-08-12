<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/

// To be able to access files in this CDash installation regardless
// of getcwd() value:
//
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

require_once("cdash/common.php");
require_once("cdash/do_submit.php");
require_once("cdash/pdo.php");


ob_start();
set_time_limit(0);
ignore_user_abort(TRUE);


// Returns true if this call to processsubmissions.php should execute the
// processing loop. Returns false if another instance of processsubmissions.php
// is already executing the loop.
//
function AcquireProcessingLock($projectid)
{
  $locked = false;

  $table_locked = pdo_query("LOCK TABLES submissionprocessor WRITE");
  if (!$table_locked)
    {
    add_log("AcquireProcessingLock", "could not lock database tables", LOG_ERR, $projectid);
    }

  if ($table_locked)
    {
    // The submissionprocessor table should have at most one row for
    // each projectid value. Expect $c to be 0 or 1 here.
    //
    $c = pdo_get_field_value(
      "SELECT COUNT(*) FROM submissionprocessor WHERE projectid='".$projectid."'",
      'COUNT(*)', 0);

    $now_utc = gmdate(FMT_DATETIMESTD);
    $mypid = getmypid();

    if ($c == 0)
      {
      // No row yet for this projectid. Insert one and own the loop lock.
      //
      pdo_query("INSERT INTO submissionprocessor (projectid, pid, lastupdated, locked) ".
        "VALUES ('$projectid', '$mypid', '$now_utc', '$now_utc')");
      add_last_sql_error("AcquireProcessingLock-1");
      $locked = true;
      add_log("AcquireProcessingLock", "lock acquired -- and row created", LOG_INFO, $projectid);
      }
    else
      {
      // One row for this projectid. See if some other pid owns the lock.
      //
      if ($c != 1)
        {
        add_log("AcquireProcessingLock", "unexpected row count c='$c'", LOG_ERR, $projectid);
        }

      $row = pdo_single_row_query(
        "SELECT * FROM submissionprocessor WHERE projectid='".$projectid."'");
      $pid = $row['pid'];

      if ($pid == 0)
        {
        // No other pid owns the lock.
        // Update the one row with mypid and own the loop lock.
        //
        pdo_query("UPDATE submissionprocessor ".
          "SET pid='$mypid', lastupdated='$now_utc', locked='$now_utc' ".
          "WHERE projectid='".$projectid."'");
        add_last_sql_error("AcquireProcessingLock-2");
        $locked = true;
        add_log("AcquireProcessingLock", "lock acquired", LOG_INFO, $projectid);
        }
      else
        {
        // Another pid owns the lock and is presumably still alive
        // and processing...
        // Verify that it has not been too long since the lastupdated
        // field was updated.
        // If it was too long ago, log an error.
        //
        $lastupdated = $row['lastupdated'];

        $lastupdated_utc_ts = strtotime($lastupdated);
        $now_utc_ts = strtotime($now_utc);

        global $CDASH_SUBMISSION_PROCESSING_TIME_LIMIT;
        if ($lastupdated_utc_ts < ($now_utc_ts - $CDASH_SUBMISSION_PROCESSING_TIME_LIMIT))
          {
          add_log("AcquireProcessingLock", "other processor pid='$pid' apparently stalled, lastupdated='$lastupdated'", LOG_ERR, $projectid);
          }

        add_log("AcquireProcessingLock", "lock not acquired, owned by other pid='$pid'", LOG_INFO, $projectid);
        }
      }

    $table_unlocked = pdo_query("UNLOCK TABLES");
    if (!$table_unlocked)
      {
      add_log("AcquireProcessingLock", "could not unlock database tables", LOG_ERR, $projectid);
      }
    }

  return $locked;
}


// Releases the lock we own in the submissionprocessor table by
// setting the pid field of this projectid's row to 0.
//
function ReleaseProcessingLock($projectid)
{
  $unlocked = false;

  $table_locked = pdo_query("LOCK TABLES submissionprocessor WRITE");
  if (!$table_locked)
    {
    add_log("ReleaseProcessingLock", "could not lock database tables", LOG_ERR, $projectid);
    }

  if ($table_locked)
    {
    $now_utc = gmdate(FMT_DATETIMESTD);
    $mypid = getmypid();

    $row = pdo_single_row_query(
      "SELECT * FROM submissionprocessor WHERE projectid='".$projectid."'");
    $pid = $row['pid'];

    if ($pid == $mypid)
      {
      pdo_query("UPDATE submissionprocessor ".
        "SET pid='0', lastupdated='$now_utc', locked='1980-01-01 00:00:00' ".
        "WHERE projectid='".$projectid."'");
      add_last_sql_error("ReleaseProcessingLock-1");
      $unlocked = true;
      add_log("ReleaseProcessingLock", "lock released", LOG_INFO, $projectid);
      }
    else
      {
      add_log("ReleaseProcessingLock", "lock not released, unexpected pid mismatch: pid='$pid' mypid='$mypid' - attempt to unlock a lock we don't own...", LOG_ERR, $projectid);
      }

    $table_unlocked = pdo_query("UNLOCK TABLES");
    if (!$table_unlocked)
      {
      add_log("ReleaseProcessingLock", "could not unlock database tables", LOG_ERR, $projectid);
      }
    }

  return $unlocked;
}


// SetLockLastUpdatedTime
//
function SetLockLastUpdatedTime($projectid)
{
  $now_utc = gmdate(FMT_DATETIMESTD);

  if (pdo_query("UPDATE submissionprocessor ".
    "SET lastupdated='$now_utc' ".
    "WHERE projectid='".$projectid."'"))
    {
    add_log("SetLockLastUpdatedTime", "lock lastupdated='$now_utc'", LOG_INFO, $projectid);
    return true;
    }
  else
    {
    add_last_sql_error("SetLockLastUpdatedTime-1");
    add_log("SetLockLastUpdatedTime", "error setting lock lastupdated='$now_utc'", LOG_ERR, $projectid);
    return false;
    }
}


// For submissions that are "currently processing" but started processing a
// "long time" ago... consider them stalled and reset them to "not processing"
// so that the next processing loop will try again.
//
// Returns the number of records reset, or -1 if there was a query error.
//
function ResetApparentlyStalledSubmissions($projectid)
{
  global $CDASH_SUBMISSION_PROCESSING_TIME_LIMIT;

  $stall_time = gmdate(FMT_DATETIMESTD, time()-$CDASH_SUBMISSION_PROCESSING_TIME_LIMIT);

  $result = pdo_query("UPDATE submission SET status=0 WHERE status=1 AND projectid='$projectid' AND started<'$stall_time' AND finished='1980-01-01 00:00:00'");
  add_last_sql_error("ResetApparentlyStalledSubmissions-1");

  $nrows = pdo_affected_rows($result);
  if ($nrows > 0)
    {
    add_log("ResetApparentlyStalledSubmissions",
      "$nrows submission records assumed stalled, reset to status=0",
      LOG_ERR, $projectid);
    }

  return $nrows;
}


// Process submissions from the 'submission' table with projectid and status=0.
//
// Process them in the order received, and continue processing until there are
// no more with status=0.
//
function ProcessSubmissions($projectid)
{
  $qs = "SELECT id, filename, filesize, attempts FROM submission WHERE projectid='".
    $projectid."' AND status=0 ORDER BY id LIMIT 1";

  add_log("ProcessSubmission", "querying before loop", LOG_INFO, $projectid);
  $query = pdo_query($qs);
  add_last_sql_error("ProcessSubmissions-1");
  $iterations = 0;

  @$sleep_in_loop = $_GET['sleep_in_loop'];

  $n = pdo_num_rows($query);
  while ($n > 0)
  {
    if ($sleep_in_loop)
      {
      sleep($sleep_in_loop);
      }

    $query_array = pdo_fetch_array($query);
    add_last_sql_error("ProcessSubmissions-1.5");

    add_log("ProcessSubmission", "query_array: ".print_r($query_array, true), LOG_INFO, $projectid);

    $submission_id = $query_array['id'];
    $filename = $query_array['filename'];
    $new_attempts = $query_array['attempts'] + 1;

    // Mark it as status=1 (processing) and record started time:
    //
    $now_utc = gmdate(FMT_DATETIMESTD);
    pdo_query("UPDATE submission SET status=1, started='$now_utc', lastupdated='$now_utc', attempts=$new_attempts WHERE id='".$submission_id."'");
    add_last_sql_error("ProcessSubmissions-2");

    // Mark the submissionprocessing table each time through the loop so that
    // we do not become known as an "apparently stalled" processor...
    //
    SetLockLastUpdatedTime($projectid);

    $mem_used = memory_get_usage();
    $logstring = "iterations='$iterations' mem_used='$mem_used'";
    add_log("ProcessSubmission", "$logstring", LOG_INFO, $projectid);

    add_log("ProcessSubmission", "connection_status='".connection_status()."'", LOG_INFO, $projectid);
    add_log("ProcessSubmission", "connection_aborted='".connection_aborted()."'", LOG_INFO, $projectid);

    echo "# ============================================================================\n";
    echo "# $logstring\n";
    echo 'Marked submission as started'."\n";
    echo print_r($query_array, true) . "\n";

    add_log("ProcessSubmission", "calling pdo_free_result", LOG_INFO, $projectid);
    pdo_free_result($query_array);

    $fp = fopen($filename, 'r');
    if(!$fp)
    {
      echo "Unexpected: no such file, checking in ../\n";
      // check in parent dir also
      $filename = "../$filename";
      $fp = fopen($filename, 'r');
    }
    if($fp)
    {
      echo 'Calling do_submit'."\n";
      add_log("ProcessSubmission", "calling do_submit", LOG_INFO, $projectid);
      do_submit($fp, $projectid, '', false);
      add_log("ProcessSubmission", "calling fclose", LOG_INFO, $projectid);
      fclose($fp);
      // delete the temporary backup file since we now have a better-named one
      echo 'Calling unlink (' . $filename . ')'."\n";
      add_log("ProcessSubmission", "calling unlink", LOG_INFO, $projectid);
      unlink($filename);
      $new_status = 2; // done, did call do_submit
    }
    else
    {
      echo 'Calling add_log'."\n";
      add_log("ProcessSubmission", "Cannot open file '".$filename."'", LOG_ERR, $projectid);
      $new_status = 3; // done, did *NOT* call do_submit
    }

    // Mark it as done and record finished time:
    //
    echo 'Marking submission as finished'."\n";
    $now_utc = gmdate(FMT_DATETIMESTD);
    add_log("ProcessSubmission", "marking status=$new_status", LOG_INFO, $projectid);
    pdo_query("UPDATE submission SET status=$new_status, finished='$now_utc', lastupdated='$now_utc' WHERE id='".$submission_id."'");
    add_last_sql_error("ProcessSubmissions-3");

    echo 'Re-querying for more submissions'."\n";
    add_log("ProcessSubmission", "querying for more submissions", LOG_INFO, $projectid);
    $query = pdo_query($qs);
    add_last_sql_error("ProcessSubmissions-4");
    $n = pdo_num_rows($query);
    add_log("ProcessSubmission", "got $n rows", LOG_INFO, $projectid);
    $iterations = $iterations + 1;
  }

  $mem_used = memory_get_usage();
  $logstring = "DONE iterations='$iterations' mem_used='$mem_used'";
  add_log("ProcessSubmission", "$logstring", LOG_INFO, $projectid);
}


// Retire submission records after a week. But keep them around for a week
// to enable analyzing submission timings.
//
function DeleteOldSubmissionRecords($projectid)
{
  // Number of seconds in an 8-day week:
  //
  $seconds = 691200; // == 60 * 60 * 24 * 8;

  $one_week_ago_utc = gmdate(FMT_DATETIMESTD, time()-$seconds);

  pdo_query("DELETE FROM submission WHERE (status=2 OR status=3) AND projectid='$projectid' AND finished<'$one_week_ago_utc' AND finished!='1980-01-01 00:00:00'");
  add_last_sql_error("ProcessSubmissions-5");
}


// Parse script arguments. This file can be run in a web browser or called
// from the php command line executable.
//
echo "<pre>";
echo "begin processSubmissions.php\n";

$force = 0;

if (isset($argc) && $argc>1)
{
  echo "argc='" . $argc . "'\n";
  for ($i = 0; $i < $argc; ++$i)
  {
    echo "argv[" . $i . "]='" . $argv[$i] . "'\n";

    if ($argv[$i] == '--force')
    {
      $force = 1;
    }
  }

  $projectid = $argv[1];
}
else
{
  echo "no argc, context is web browser or some other non-command-line...\n";
  @$projectid = $_GET['projectid'];
  @$force = $_GET['force'];
}

if(!is_numeric($projectid))
{
  echo "projectid/argv[1] should be a number\n";
  echo "</pre>";
  add_log("ProcessSubmission", "projectid '".$projectid."' should be a number",
    LOG_ERR, $projectid);
  return;
}


// Catch any fatal errors during processing
//
register_shutdown_function('PHPErrorHandler', $projectid);


echo "projectid='$projectid'\n";
echo "force='$force'\n";

if (AcquireProcessingLock($projectid))
{
  echo "Called AcquireProcessingLock\n";

  ResetApparentlyStalledSubmissions($projectid);
  echo "Done with ResetApparentlyStalledSubmissions\n";

  ProcessSubmissions($projectid);
  echo "Done with ProcessSubmissions\n";

  DeleteOldSubmissionRecords($projectid);
  echo "Done with DeleteOldSubmissionRecords\n";

  ReleaseProcessingLock($projectid);
  echo "Called ReleasedProcessingLock\n";
}
else
{
  echo "Could not lock for processing\n";
  echo "Another process is already processing or there was a locking error\n";

//  add_log("ProcessSubmission", "could not lock for processing",
//          LOG_INFO, $projectid);
}

echo "end processSubmissions.php\n";
echo "</pre>";

ob_end_flush();
?>
