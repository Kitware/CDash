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

require_once(dirname(dirname(__DIR__))."/config/config.php");
require_once("include/common.php");
require_once("include/do_submit.php");
require_once("include/fnProcessFile.php");
require_once("include/pdo.php");


ob_start();
set_time_limit(0);
ignore_user_abort(true);


// Returns true if this call to processsubmissions.php should execute the
// processing loop. Returns false if another instance of processsubmissions.php
// is already executing the loop for this projectid.
//
function AcquireProcessingLock($projectid, $force)
{
    $locked = false;

    $tables = array();
    $tables[] = 'submissionprocessor';
    $table_locked = pdo_lock_tables($tables);
    if (!$table_locked) {
        add_log("could not lock database tables", "AcquireProcessingLock",
      LOG_ERR, $projectid);
    }

    if ($table_locked) {
        // The submissionprocessor table should have at most one row for
    // each projectid value. Expect $c to be 0 or 1 here.
    //
    $c = pdo_get_field_value(
      "SELECT COUNT(*) AS c FROM submissionprocessor ".
      "WHERE projectid='".$projectid."'",
      'c', 0);

        $now_utc = gmdate(FMT_DATETIMESTD);
        $mypid = getmypid();

        if ($c == 0) {
            // No row yet for this projectid. Insert one and own the loop lock.
      //
      pdo_query(
        "INSERT INTO submissionprocessor (projectid, pid, lastupdated, locked) ".
        "VALUES ('$projectid', '$mypid', '$now_utc', '$now_utc')");
            add_last_sql_error("AcquireProcessingLock-1");
            $locked = true;
        } else {
            // One row for this projectid. See if some other pid owns the lock.
      //
      if ($c != 1) {
          add_log("unexpected row count c='$c'", "AcquireProcessingLock",
          LOG_ERR, $projectid);
      }

            $row = pdo_single_row_query(
        "SELECT * FROM submissionprocessor WHERE projectid='".$projectid."'");
            $pid = $row['pid'];

      // By default, do not acquire the lock:
      //
      $acquire_lock = false;

            if ($force) {
                add_log("taking lock: 'force' is true", "AcquireProcessingLock",
          LOG_INFO, $projectid);
                $acquire_lock = true;
            } elseif ($pid != 0) {
                // Another pid owns the lock and is presumably still alive
        // and processing...
        //
        // Verify that it has not been "too long" since the lastupdated
        // field was updated.
        //
        // If it was too long ago, assume something bad has happened, and take
        // the lock from that process. Ideally, we'd have a way to measure if
        // $pid represents a still running process, and only take the lock if
        // $pid is definitely *not* running anymore...
        //
        $lastupdated = $row['lastupdated'];

                $lastupdated_utc_ts = strtotime($lastupdated);
                $now_utc_ts = strtotime($now_utc);

                global $CDASH_SUBMISSION_PROCESSING_TIME_LIMIT;
                if ($lastupdated_utc_ts < ($now_utc_ts - $CDASH_SUBMISSION_PROCESSING_TIME_LIMIT)) {
                    //if ($pid is not presently running) // assumed, php-way to measure?
          //  {
            add_log(
              "taking lock: projectid=$projectid, other processor pid='$pid' ".
              "apparently stalled, lastupdated='$lastupdated'",
              "AcquireProcessingLock",
              LOG_ERR, $projectid);
                    $acquire_lock = true;
          //  }
                }
            } else {
                // No other pid owns the lock. OK to acquire it.
        //
        $acquire_lock = true;
            }

            if ($acquire_lock) {
                // Update the one row with mypid and own the loop lock.
        //
        pdo_query(
          "UPDATE submissionprocessor ".
          "SET pid='$mypid', lastupdated='$now_utc', locked='$now_utc' ".
          "WHERE projectid='".$projectid."'");
                add_last_sql_error("AcquireProcessingLock-2");
                $locked = true;
            }

            $table_unlocked = pdo_unlock_tables();
            if (!$table_unlocked) {
                add_log("could not unlock database tables", "AcquireProcessingLock",
          LOG_ERR, $projectid);
            }
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

    $tables = array();
    $tables[] = 'submissionprocessor';
    $table_locked = pdo_lock_tables($tables);
    if (!$table_locked) {
        add_log("could not lock database tables", "ReleaseProcessingLock",
      LOG_ERR, $projectid);
    }

    if ($table_locked) {
        $now_utc = gmdate(FMT_DATETIMESTD);
        $mypid = getmypid();

        $row = pdo_single_row_query(
      "SELECT * FROM submissionprocessor WHERE projectid='".$projectid."'");
        $pid = $row['pid'];

        if ($pid == $mypid) {
            pdo_query("UPDATE submissionprocessor ".
        "SET pid='0', lastupdated='$now_utc', locked='1980-01-01 00:00:00' ".
        "WHERE projectid='".$projectid."'");
            add_last_sql_error("ReleaseProcessingLock-1");
            $unlocked = true;
        } else {
            add_log(
        "lock not released, unexpected pid mismatch: pid='$pid' mypid='$mypid' - attempt to unlock a lock we don't own...",
        "ReleaseProcessingLock",
        LOG_ERR, $projectid);
        }

        $table_unlocked = pdo_unlock_tables();
        if (!$table_unlocked) {
            add_log("could not unlock database tables", "ReleaseProcessingLock",
        LOG_ERR, $projectid);
        }
    }

    return $unlocked;
}


// ProcessOwnsLock
//
function ProcessOwnsLock($projectid, $pid)
{
    $owner_pid = pdo_get_field_value(
    "SELECT pid FROM submissionprocessor WHERE projectid='".$projectid."'",
    'pid', 0);
    return $owner_pid == $pid;
}


// SetLockLastUpdatedTime
//
function SetLockLastUpdatedTime($projectid)
{
    $now_utc = gmdate(FMT_DATETIMESTD);

    if (pdo_query("UPDATE submissionprocessor ".
    "SET lastupdated='$now_utc' ".
    "WHERE projectid='".$projectid."'")) {
        return true;
    }

    add_last_sql_error("SetLockLastUpdatedTime-1");
    return false;
}


// For submissions that are "currently processing" but started processing a
// "long time" ago... consider them stalled and reset them to "not processing"
// so that the next processing loop will try again.
//
// Known status values are as follows:
//   0 = not yet processing (or reset)
//   1 = actively processing (or stalled and awaiting reset)
//   2 = finished processing normally
//   3 = could not process, could not read xml file
//   4 = error caught during processing, partially processed
//   5 = too many attempts, gave up, partially processed possibly many times
//
// Returns the number of records reset, or -1 if there was a query error.
//
function ResetApparentlyStalledSubmissions($projectid)
{
    global $CDASH_SUBMISSION_PROCESSING_TIME_LIMIT;

    $stall_time = gmdate(FMT_DATETIMESTD, time()-$CDASH_SUBMISSION_PROCESSING_TIME_LIMIT);

    $result = pdo_query("UPDATE submission SET status=0 ".
    "WHERE status=1 AND projectid='$projectid' AND ".
    "started<'$stall_time' AND finished='1980-01-01 00:00:00'");
    add_last_sql_error("ResetApparentlyStalledSubmissions-1");

    $nrows = pdo_affected_rows($result);
    if ($nrows > 0) {
        add_log(
      "$nrows submission records assumed stalled, reset to status=0",
      "ResetApparentlyStalledSubmissions",
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
    $qs = "SELECT id, filename, filesize, filemd5sum, attempts FROM submission ".
    "WHERE projectid='".$projectid."' AND status=0 ORDER BY id LIMIT 1";

    $query = pdo_query($qs);
    add_last_sql_error("ProcessSubmissions-1");
    $iterations = 0;
    $mypid = getmypid();

    @$sleep_in_loop = $_GET['sleep_in_loop'];
    @$intentional_nonreturning_submit = $_GET['intentional_nonreturning_submit'];

    $n = pdo_num_rows($query);
    while ($n > 0) {
        if ($sleep_in_loop) {
            sleep($sleep_in_loop);
        }

        $query_array = pdo_fetch_array($query);
        add_last_sql_error("ProcessSubmissions-1.5");

        pdo_free_result($query);

    // Verify that *this* process still owns the lock.
    //
    // If not, log a message and return, presuming that the process that took
    // the lock is now looping over pending submissions.
    //
    if (!ProcessOwnsLock($projectid, $mypid)) {
        add_log(
        "pid '$mypid' does not own lock anymore: abandoning loop...",
        "ProcessSubmissions",
        LOG_INFO, $projectid);
        return false;
    }

        $submission_id = $query_array['id'];
        $filename = $query_array['filename'];
        $new_attempts = $query_array['attempts'] + 1;
        $md5 = $query_array['filemd5sum'];

    // Mark the submissionprocessing table each time through the loop so that
    // we do not become known as an "apparently stalled" processor...
    //
    SetLockLastUpdatedTime($projectid);

        global $CDASH_SUBMISSION_PROCESSING_MAX_ATTEMPTS;
        if ($new_attempts > $CDASH_SUBMISSION_PROCESSING_MAX_ATTEMPTS) {
            add_log("Too many attempts to process '".$filename."'",
        "ProcessSubmissions",
        LOG_ERR, $projectid);
            $new_status = 5; // done, called do_submit too many times already
        } else {
            // Mark it as status=1 (processing) and record started time:
      //
      $now_utc = gmdate(FMT_DATETIMESTD);
            pdo_query("UPDATE submission SET status=1, started='$now_utc', ".
        "lastupdated='$now_utc', attempts=$new_attempts ".
        "WHERE id='".$submission_id."'");
            add_last_sql_error("ProcessSubmissions-2");

      // Record id in global so that we can mark it as "error status" if we
      // get thrown to the error handler...
      //
      global $PHP_ERROR_SUBMISSION_ID;
            $PHP_ERROR_SUBMISSION_ID = $submission_id;

            if ($intentional_nonreturning_submit) {
                // simulate "error occurred" during do_submit: status will be set
        // to 4 in error handler...
        trigger_error(
          'ProcessFile: intentional_nonreturning_submit is on',
          E_USER_ERROR);
            }

            $new_status = ProcessFile($projectid, $filename, $md5);
        }

        $PHP_ERROR_SUBMISSION_ID = 0;

    // Mark it as done with $new_status and record finished time:
    //
    $now_utc = gmdate(FMT_DATETIMESTD);
        pdo_query(
      "UPDATE submission SET status=$new_status, finished='$now_utc', ".
      "lastupdated='$now_utc' WHERE id='".$submission_id."'");
        add_last_sql_error("ProcessSubmissions-3");

    // Query for more... Continue processing while there are records to
    // process:
    //
    $query = pdo_query($qs);
        add_last_sql_error("ProcessSubmissions-4");
        $n = pdo_num_rows($query);
        $iterations = $iterations + 1;
    }

    return true;
}


// Retire submission records after a week (by default).
// But keep them around for a week to enable analyzing submission timings.
//
function DeleteOldSubmissionRecords($projectid)
{
    global $CDASH_ASYNC_EXPIRATION_TIME;

    $delete_time =
    gmdate(FMT_DATETIMESTD, time() - $CDASH_ASYNC_EXPIRATION_TIME);

    $ids = pdo_all_rows_query("SELECT id FROM submission WHERE ".
    "(status=2 OR status=3 OR status=4 OR status=5) AND ".
    "projectid='$projectid' AND finished<'$delete_time' AND ".
    "finished!='1980-01-01 00:00:00'");

    $count = count($ids);
    if (0 == $count) {
        // Nothing to delete!
    return;
    }

    $idset = "(";
    foreach ($ids as $id_row) {
        $id = $id_row["id"];
        $idset .= "'$id', ";
    }
  // Avoid conditional ", " emission in the loop. OK to repeat an
  // element in this DELETE IN type of query:
  $idset .= "'".$ids[0]["id"]."')";

    pdo_delete_query("DELETE FROM submission WHERE id IN ".$idset);
    pdo_delete_query("DELETE FROM client_jobschedule2submission WHERE submissionid IN ".$idset);
    pdo_delete_query("DELETE FROM submission2ip WHERE submissionid IN ".$idset);
}


// Provide an error handler that can give up on a submission if a
// fatal PHP error occurs while processing a file.
//
function ProcessSubmissionsErrorHandler($projectid)
{
    $needs_clean_up = false;

    if ($error = error_get_last()) {
        switch ($error['type']) {
      case E_ERROR:
      case E_CORE_ERROR:
      case E_COMPILE_ERROR:
      case E_USER_ERROR:
        $needs_clean_up = true;
      break;
      }
    }

    if ($needs_clean_up) {
        // Mark the current submission, if any, as 'failed with error'
    //
    global $PHP_ERROR_SUBMISSION_ID;
        if (0 != $PHP_ERROR_SUBMISSION_ID) {
            $now_utc = gmdate(FMT_DATETIMESTD);
            pdo_query(
        "UPDATE submission SET status=4, finished='$now_utc', ".
        "lastupdated='$now_utc' WHERE id='".$PHP_ERROR_SUBMISSION_ID."'");
            add_last_sql_error("ProcessSubmissionsErrorHandler-1");

            echo "ProcessSubmissionsErrorHandler: error processing submission id $PHP_ERROR_SUBMISSION_ID\n";
            add_log(
        "error processing submission id $PHP_ERROR_SUBMISSION_ID",
        'ProcessSubmissionsErrorHandler',
        LOG_ERR, $projectid);
        }

    // Call ReleaseProcessingLock since an error occurred before the expected
    // call to it at the bottom of the script:
    //
    if (ReleaseProcessingLock($projectid)) {
        echo "ProcessSubmissionsErrorHandler: ReleasedProcessingLock($projectid) returned true\n";
    } else {
        echo "ProcessSubmissionsErrorHandler: ReleasedProcessingLock($projectid) returned false\n";
    }
    }

  // Call the main CDash error handler for its default logging behavior:
  //
  PHPErrorHandler($projectid);
}


// Parse script arguments. This file can be run in a web browser or called
// from the php command line executable.
//
// When called by command line, argv[1] is the projectid, and argv[2] may
// optionally be "--force" to force acquiring the processing lock.
// If "--force" is given, $force is 1, otherwise it's 0.
//
// When called by http, use "?projectid=1&force=1" to pass the info through
// php's _GET array. Use value 0 or 1 for force. If omitted, $force is 0.
//
echo "<pre>";
echo "begin processSubmissions.php\n";

$force = 0;

if (isset($argc) && $argc>1) {
    echo "argc, context is php command-line invocation...\n";
    echo "argc='" . $argc . "'\n";
    for ($i = 0; $i < $argc; ++$i) {
        echo "argv[" . $i . "]='" . $argv[$i] . "'\n";

        if ($argv[$i] == '--force') {
            $force = 1;
        }
    }

    $projectid = $argv[1];
} else {
    echo "no argc, context is web browser or some other non-command-line...\n";
    @$projectid = $_GET['projectid'];
    if ($projectid != null) {
        $projectid = pdo_real_escape_numeric($projectid);
    }

    @$force = $_GET['force'];
}

if (!is_numeric($projectid)) {
    echo "projectid/argv[1] should be a number\n";
    echo "</pre>";
    add_log("projectid '".$projectid."' should be a number",
    "ProcessSubmission",
    LOG_ERR, $projectid);
    return;
}


// Catch any fatal errors during processing
//
register_shutdown_function('ProcessSubmissionsErrorHandler', $projectid);


echo "projectid='$projectid'\n";
echo "force='$force'\n";

if (AcquireProcessingLock($projectid, $force)) {
    echo "AcquireProcessingLock returned true\n";

    ResetApparentlyStalledSubmissions($projectid);
    echo "Done with ResetApparentlyStalledSubmissions\n";

    ProcessSubmissions($projectid);
    echo "Done with ProcessSubmissions\n";

    DeleteOldSubmissionRecords($projectid);
    echo "Done with DeleteOldSubmissionRecords\n";

    if (ReleaseProcessingLock($projectid)) {
        echo "ReleasedProcessingLock returned true\n";
    } else {
        echo "ReleasedProcessingLock returned false\n";
    }
} else {
    echo "AcquireProcessingLock returned false\n";
    echo "Another process is already processing or there was a locking error\n";
}

echo "end processSubmissions.php\n";
echo "</pre>";

ob_end_flush();
