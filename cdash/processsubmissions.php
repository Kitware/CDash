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

// Put the CDash root directory in the path
$splitchar = '/';
if(DIRECTORY_SEPARATOR == '\\')
{
  $splitchar='\\\\';
}
$path = join(array_slice(split( $splitchar ,dirname(__FILE__)),0,-1),DIRECTORY_SEPARATOR);
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

require_once("cdash/common.php");
require_once("cdash/do_submit.php");
require_once("cdash/pdo.php");


set_time_limit(0);


// Returns the 'id' of the submission that is currently being processed for
// projectid. -1 if none.
//
function GetCurrentSubmissionID($projectid)
{
  $id = pdo_get_field_value(
    "SELECT id FROM submission WHERE projectid='".$projectid."' AND status=1",
    'id', -1);
  return $id;
}


// Returns TRUE if submission started less than $recent seconds ago.
// FALSE otherwise.
//
function DidSubmissionStartRecently($submission_id, $recent)
{
  $started_utc = pdo_get_field_value(
    "SELECT started FROM submission WHERE id='".$submission_id."'",
    'started', '1980-01-01 00:00:00');
  $now_utc = gmdate(FMT_DATETIMESTD);

  $started_utc_ts = strtotime($started_utc);
  $now_utc_ts = strtotime($now_utc);

  $age = $now_utc_ts - $started_utc_ts;

  if($age < $recent)
  {
    return TRUE;
  }

  return FALSE;
}


// Process submissions from the 'submission' table with projectid and status=0.
//
// Process them in the order received, and continue processing until there are
// no more with status=0.
//
function ProcessSubmissions($projectid)
{
  $qs = "SELECT id, filename, attempts FROM submission WHERE projectid='".
    $projectid."' AND status=0 ORDER BY id LIMIT 1";

  $query = pdo_query($qs);

  while (pdo_num_rows($query) > 0)
  {
    $query_array = pdo_fetch_array($query);

    $submission_id = $query_array['id'];
    $filename = $query_array['filename'];
    $new_attempts = $query_array['attempts'] + 1;

    # Mark it as status=1 (processing) and record started time:
    #
    $now_utc = gmdate(FMT_DATETIMESTD);
    pdo_query("UPDATE submission SET status=1, started='$now_utc', lastupdated='$now_utc', attempts=$new_attempts WHERE id='".$submission_id."'");

    echo "# ============================================================================\n";
    echo 'Marked submission as started'."\n";
    echo print_r($query_array, true) . "\n";

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
      do_submit($fp, $projectid, '', false);
      // delete the temporary backup file since we now have a better-named one
      echo 'Calling unlink (' . $filename . ')'."\n";
      unlink($filename);
      $new_status = 2; // done, did call do_submit
    }
    else
    {
      echo 'Calling add_log'."\n";
      add_log("ProcessSubmission", "Cannot open file '".$filename."'", LOG_ERR, $projectid);
      $new_status = 3; // done, did *NOT* call do_submit
    }

    # Mark it as done and record finished time:
    #
    echo 'Marking submission as finished'."\n";
    $now_utc = gmdate(FMT_DATETIMESTD);
    pdo_query("UPDATE submission SET status=$new_status, finished='$now_utc', lastupdated='$now_utc' WHERE id='".$submission_id."'");

    echo 'Re-querying for more submissions'."\n";
    $query = pdo_query($qs);
  }
}


// Retire submission records after a week. But keep them around for a week
// to enable analyzing submission timings.
//
function DeleteOldSubmissionRecords()
{
  // Number of seconds in a week:
  //
  $seconds = 604800; // == 60 * 60 * 24 * 7;

  $one_week_ago_utc = gmdate(FMT_DATETIMESTD, time()-$seconds);

  pdo_query("DELETE FROM submission WHERE status>1 AND finished<'$one_week_ago_utc'");
}


// Parse script arguments. This file can be run in a web browser or called
// from the php command line executable.
//
$force = 0;

if (isset($argc) && $argc>1)
{
  echo "argc: '" . $argc . "'\n";
  for ($i = 0; $i < $argc; ++$i)
  {
    echo "argv[" . $i . "]: '" . $argv[$i] . "'\n";

    if ($argv[$i] == '--force')
    {
      $force = 1;
    }
  }

  $projectid = $argv[1];
}
else
{
  echo "<pre>";
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


// Check if someone is already processing submissions for this project.
// If so, and we do not suspect the processor was killed, return early.
//
$current_submission_id = GetCurrentSubmissionID($projectid);
if (-1 != $current_submission_id)
{
  global $CDASH_SUBMISSION_PROCESSING_TIME_LIMIT;

  $started_recently = DidSubmissionStartRecently($current_submission_id,
    $CDASH_SUBMISSION_PROCESSING_TIME_LIMIT);

  if ($started_recently && !$force)
  {
    echo "submission $current_submission_id started recently: Give a few minutes to finish...\n";
    echo "</pre>";
    add_log("ProcessSubmission", "submission $current_submission_id started recently",
      LOG_INFO, $projectid);
    return;
  }

  // "Current" or "in progress"... but not started recently...
  // Must have been killed while processing. Reset status to 0 and try again.
  //
  $now_utc = gmdate(FMT_DATETIMESTD);
  pdo_query("UPDATE submission SET status=0, lastupdated='$now_utc' WHERE id='".$current_submission_id."'");
}


echo "projectid='$projectid'\n";
echo "force='$force'\n";

ProcessSubmissions($projectid);
echo "Done with ProcessSubmissions\n";

DeleteOldSubmissionRecords();
echo "Done with DeleteOldSubmissionRecords\n";

echo "</pre>";
?>
