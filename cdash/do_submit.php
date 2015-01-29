<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: submit.php 1582 2009-03-19 21:05:00Z jjomier $
  Language:  PHP
  Date:      $Date: 2009-03-19 17:05:00 -0400 (Thu, 19 Mar 2009) $
  Version:   $Revision: 1582 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
//error_reporting(0); // disable error reporting

include("cdash/ctestparser.php");
include_once("cdash/common.php");
include_once("cdash/createRSS.php");
include("cdash/sendemail.php");

function do_submit($filehandle, $projectid, $expected_md5='', $do_checksum=true,
                   $submission_id=0)
{
  include('cdash/config.php');

  // We find the daily updates
  // If we have php curl we do it asynchronously
  if(function_exists("curl_init") == TRUE)
    {
    $currentURI = get_server_URI(true);
    $request = $currentURI."/cdash/dailyupdatescurl.php?projectid=".$projectid;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $request);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    if ($CDASH_USE_HTTPS)
      {
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
      }
    curl_exec($ch);
    curl_close($ch);
    }
  else // synchronously
    {
    include("cdash/dailyupdates.php");
    addDailyChanges($projectid);
    }

  if($CDASH_USE_LOCAL_DIRECTORY&&file_exists("local/submit.php"))
    {
    include("local/submit.php");
    }

  $scheduleid = 0;
  if($submission_id !== 0)
    {
    $row = pdo_single_row_query(
      "SELECT scheduleid from client_jobschedule2submission WHERE submissionid=$submission_id");
    if(!empty($row))
      {
      $scheduleid = $row[0];
      }
    }
  else if(isset($_GET["clientscheduleid"]))
    {
    $scheduleid = pdo_real_escape_numeric($_GET["clientscheduleid"]);
    }

  // Parse the XML file
  $handler = ctest_parse($filehandle,$projectid, $expected_md5, $do_checksum, $scheduleid);
  //this is the md5 checksum fail case
  if($handler == FALSE)
    {
    //no need to log an error since ctest_parse already did
    return;
    }

  // Send the emails if necessary
  if($handler instanceof UpdateHandler)
    {
    send_update_email($handler, $projectid);
    }
  if($handler instanceof TestingHandler ||
     $handler instanceof BuildHandler ||
     $handler instanceof ConfigureHandler ||
     $handler instanceof DynamicAnalysisHandler)
    {
    sendemail($handler, $projectid);
    }

  global $CDASH_ENABLE_FEED;
  if ($CDASH_ENABLE_FEED)
    {
    // Create the RSS feed
    CreateRSSFeed($projectid);
    }
}

/** Asynchronous submission */
function do_submit_asynchronous($filehandle, $projectid, $expected_md5='')
{
  include('cdash/config.php');
  include('cdash/version.php');

  do
    {
    $filename = $CDASH_BACKUP_DIRECTORY."/".mt_rand().".xml";
    $fp = @fopen($filename, 'x');
    }
  while(!$fp);
  fclose($fp);
  unset($fp);

  $outfile = fopen($filename, 'w');

  // Save the file in the backup directory
  while(!feof($filehandle))
    {
    $content = fread($filehandle, 8192);
    if (fwrite($outfile, $content) === FALSE)
      {
      echo "ERROR: Cannot write to file ($filename)";
      add_log("Cannot write to file ($filename)", "do_submit_asynchronous",
        LOG_ERR, $projectid);
      fclose($outfile);
      unset($outfile);
      return;
      }
    }
  fclose($outfile);
  unset($outfile);

  // Sends the file size to the local parser
  if($CDASH_USE_LOCAL_DIRECTORY && file_exists("local/ctestparser.php"))
    {
    require_once("local/ctestparser.php");
    $localParser = new LocalParser();
    $filesize = filesize($filename);
    $localParser->SetFileSize($projectid,$filesize);
    }

  $md5sum = md5_file($filename);
  $md5error = false;

  echo "<cdash version=\"".$CDASH_VERSION."\">\n";
  if($expected_md5 == '' || $expected_md5 == $md5sum)
    {
    echo "  <status>OK</status>\n";
    echo "  <message></message>\n";
    }
  else
    {
    echo "  <status>ERROR</status>\n";
    echo "  <message>Checksum failed for file.  Expected $expected_md5 but got $md5sum.</message>\n";
    $md5error = true;
    }
  echo "  <md5>$md5sum</md5>\n";
  echo "</cdash>\n";

  if($md5error)
    {
    add_log("Checksum failure on file: $filename", "do_submit_asynchronous",
      LOG_ERR, $projectid);
    return;
    }

  $bytes = filesize($filename);

  // Insert the filename in the database
  $now_utc = gmdate(FMT_DATETIMESTD);
  pdo_query("INSERT INTO submission (filename,projectid,status,attempts,filesize,filemd5sum,created) ".
    "VALUES ('".$filename."','".$projectid."','0','0','$bytes','$md5sum','$now_utc')");

  // Get the ID associated with this submission.  We may need to reference it
  // later if this is a CDash@home (client) submission.
  $submissionid = pdo_insert_id('submission');

  // We find the daily updates
  // If we have php curl we do it asynchronously
  if(function_exists("curl_init") == TRUE)
    {
    $currentURI = get_server_URI(true);
    $request = $currentURI."/cdash/dailyupdatescurl.php?projectid=".$projectid;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $request);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    if ($CDASH_USE_HTTPS)
      {
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
      }
    curl_exec($ch);
    curl_close($ch);

    $clientscheduleid = isset($_GET["clientscheduleid"]) ? pdo_real_escape_numeric($_GET["clientscheduleid"]) : 0;
    if($clientscheduleid !== 0)
      {
      pdo_query("INSERT INTO client_jobschedule2submission (scheduleid,submissionid) ".
        "VALUES ('$clientscheduleid','$submissionid')");
      }

    // Save submitter IP in the database in the async case, so we have a valid
    // IP at Site::Insert time when processing rather than 'localhost's IP:
    pdo_insert_query("INSERT INTO submission2ip (submissionid, ip) ".
        "VALUES ('$submissionid', '".$_SERVER['REMOTE_ADDR']."')");

    // Call process submissions via cURL.
    trigger_process_submissions($projectid);
    }
  else // synchronously
    {
    add_log(
      "Cannot submit asynchronously: php curl_init function does not exist",
      "do_submit_asynchronous",
      LOG_ERR, $projectid);
    }
}

/** Function to deal with the external tool mechanism */
function post_submit()
{
  include("models/buildfile.php");

  // We expect POST to contain the following values.
  $vars = array('project','build','stamp','site','track','type','starttime','endtime','datafilesmd5');
  foreach($vars as $var)
    {
    if(!isset($_POST[$var]) || empty($_POST[$var]))
      {
      $response_array['status'] = 1;
      $response_array['description'] = 'Variable \''.$var.'\' not set but required.';
      echo json_encode($response_array);
      return;
      }
    }

  $projectname = htmlspecialchars(pdo_real_escape_string($_POST['project']));
  $buildname = htmlspecialchars(pdo_real_escape_string($_POST['build']));
  $buildstamp = htmlspecialchars(pdo_real_escape_string($_POST['stamp']));
  $sitename = htmlspecialchars(pdo_real_escape_string($_POST['site']));
  $track = htmlspecialchars(pdo_real_escape_string($_POST['track']));
  $type = htmlspecialchars(pdo_real_escape_string($_POST['type']));
  $starttime = htmlspecialchars(pdo_real_escape_string($_POST['starttime']));
  $endtime = htmlspecialchars(pdo_real_escape_string($_POST['endtime']));

  // Check if we have the CDash@Home scheduleid
  $scheduleid=0;
  if(isset($_POST["clientscheduleid"]))
    {
    $scheduleid = pdo_real_escape_numeric($_POST["clientscheduleid"]);
    }

  // Add the build
  $build = new Build();

  $build->ProjectId = get_project_id($projectname);
  $build->StartTime = gmdate(FMT_DATETIME, $starttime);
  $build->EndTime = gmdate(FMT_DATETIME, $endtime);
  $build->SubmitTime = gmdate(FMT_DATETIME);
  $build->Name = $buildname;
  $build->InsertErrors = false; // we have no idea if we have errors at this point
  $build->SetStamp($buildstamp);

  // Get the site id
  $site = new Site();
  $site->Name = $sitename;
  $site->Insert();
  $build->SiteId = $site->Id;

  // Make this an "append" build, so that it doesn't result in a separate row
  // from the rest of the "normal" submission.
  $build->Append = true;

  // TODO: Check the labels and generator and other optional
  if(isset($_POST["generator"]))
    {
    $build->Generator = htmlspecialchars(pdo_real_escape_string($_POST['generator']));
    }

  $subprojectname = "";
  if(isset($_POST["subproject"]))
    {
    $subprojectname = htmlspecialchars(pdo_real_escape_string($_POST['subproject']));
    $build->SetSubProject($subprojectname);
    }

  // Check if this build already exists.
  $buildid = $build->GetIdFromName($subprojectname);

  // If not, add a new one.
  if ($buildid === 0)
    {
    $buildid = add_build($build,$scheduleid);
    }

  // Returns the OK submission
  $response_array['status'] = 0;
  $response_array['buildid'] = $buildid;

  $buildfile = new BuildFile();

  // Check if the files exists
  foreach($_POST['datafilesmd5'] as $md5)
    {
    $buildfile->md5 = $md5;
    $old_buildid = $buildfile->MD5Exists();
    if(!$old_buildid)
      {
      $response_array['datafilesmd5'][] = 0;
      }
    else
      {
      $response_array['datafilesmd5'][] = 1;

      // Associate this build file with the new build if it has been previously
      // uploaded.
      require_once("copy_build_data.php");
      copy_build_data($old_buildid, $buildid, $type);
      }
    }
  echo json_encode($response_array);
}

/** Function to deal with the external tool mechanism */
function put_submit_file()
{
  include("models/buildfile.php");

  // We expect GET to contain the following values:
  $vars = array('buildid','type');
  foreach($vars as $var)
    {
    if(!isset($_GET[$var]) || empty($_GET[$var]))
      {
      $response_array['status'] = 1;
      $response_array['description'] = 'Variable \''.$var.'\' not set but required.';
      echo json_encode($response_array);
      return;
      }
    }

  // Verify buildid.
  if(!is_numeric($_GET['buildid']))
    {
    $response_array['status'] = 1;
    $response_array['description'] = 'Variable \'buildid\' is not numeric.';
    echo json_encode($response_array);
    return;
    }

  // Abort early if we already have this file.
  $buildfile = new BuildFile();
  $buildfile->BuildId = $_GET['buildid'];
  $buildfile->Type = htmlspecialchars(pdo_real_escape_string($_GET['type']));
  $buildfile->md5 = htmlspecialchars(pdo_real_escape_string($_GET['md5']));
  $buildfile->Filename = htmlspecialchars(pdo_real_escape_string($_GET['filename']));
  if(!$buildfile->Insert())
    {
    $response_array['status'] = 1;
    $response_array['description'] = 'Cannot insert buildfile into database. The file might already exist.';
    echo json_encode($response_array);
    return;
    }

  // Get the ID of the project associated with this build.
  $row = pdo_single_row_query(
    "SELECT projectid FROM build WHERE id = $buildfile->BuildId");
  if(empty($row))
    {
    $response_array['status'] = 1;
    $response_array['description'] = "Cannot find projectid for build #$buildfile->BuildId";
    echo json_encode($response_array);
    return;
    }
  $projectid = $row[0];

  // Begin writing this file to the backup directory.
  global $CDASH_BACKUP_DIRECTORY;
  $uploadDir = $CDASH_BACKUP_DIRECTORY;
  $ext = pathinfo($buildfile->Filename, PATHINFO_EXTENSION);
  $filename = $uploadDir . "/" . $buildfile->BuildId . "_" . $buildfile->md5
    . ".$ext";

  if(!$handle = fopen($filename, 'w'))
    {
    $response_array['status'] = 1;
    $response_array['description'] = "Cannot open file ($filename)";
    echo json_encode($response_array);
    return;
    }

  // Read the data 1 KB at a time and write to the file.
  $putdata = fopen("php://input", "r");
  while ($data = fread($putdata, 1024))
    {
    fwrite($handle, $data);
    }
  // Close the streams.
  fclose($handle);
  fclose($putdata);

  // Check that the md5sum of the file matches what we were expecting.
  $md5sum = md5_file($filename);
  if($md5sum != $buildfile->md5)
    {
    $response_array['status'] = 1;
    $response_array['description'] =
      "md5 mismatch. expected: $buildfile->md5, received: $md5sum";
    unlink($filename);
    $buildfile->Delete();
    echo json_encode($response_array);
    return;
    }

  global $CDASH_ASYNCHRONOUS_SUBMISSION;
  if($CDASH_ASYNCHRONOUS_SUBMISSION)
    {
    // Create a new entry in the submission table for this file.
    $bytes = filesize($filename);
    $now_utc = gmdate(FMT_DATETIMESTD);
    pdo_query("INSERT INTO submission (filename,projectid,status,attempts,filesize,filemd5sum,created) ".
      "VALUES ('$filename','$projectid','0','0','$bytes','$buildfile->md5','$now_utc')");

    // Trigger the processing loop in case it's not already running.
    trigger_process_submissions($projectid);
    }
  else
    {
    // synchronous processing.
    $handle = fopen($filename, 'r');
    do_submit($handle, $projectid, $buildfile->md5, false);

    // The file is given a more appropriate name during do_submit, so we can
    // delete the old file now.
    unlink($filename);
    }

  // Returns the OK submission
  $response_array['status'] = 0;

  echo json_encode($response_array);
}

// Trigger processsubmissions.php using cURL.
function trigger_process_submissions($projectid)
{
  global $CDASH_USE_HTTPS;
  $currentURI = get_server_URI(true);
  $request = $currentURI."/cdash/processsubmissions.php?projectid=".$projectid;

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $request);
  curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 1);
  if ($CDASH_USE_HTTPS)
    {
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }

  // It's likely that the process timesout because the processing takes more
  // than 1s to run. This is OK as we just need to trigger it.
  // 28 = CURLE_OPERATION_TIMEDOUT
  if (curl_exec($ch) === false && curl_errno($ch) != 28)
    {
    add_log(
      "cURL error: ". curl_error($ch).' for request: '.$request,
      "do_submit_asynchronous",
      LOG_ERR, $projectid);
    }
  curl_close($ch);
}

?>
