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
//error_reporting(0); // disable error reporting

/** Adding some PHP include path */
$path = dirname(__FILE__);
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

// Open the database connection
include("cdash/config.php");
require_once("cdash/pdo.php");
include("cdash/do_submit.php");
include("cdash/clientsubmit.php");
include("cdash/version.php");

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
if(!$db || !pdo_select_db("$CDASH_DB_NAME",$db))
  {
  echo "ERROR: Cannot connect to database.";
  exit();
  }
set_time_limit(0);
    
// Send to the client submit
client_submit();

$expected_md5 = isset($_GET['MD5']) ? $_GET['MD5'] : '';
$file_path='php://input';
$temp_path='backup/test.tmp';
copy($file_path, $temp_path);
$fp = fopen($temp_path, 'r');
$contents = stream_get_contents($fp);
$md5sum = md5($contents);
rewind($fp);

$md5error = false;
echo "<cdash version=\"$CDASH_VERSION\">\n";
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
  add_log('Checksum failure on file: '.$_GET["filename"]);
  exit();
  }

$projectname = $_GET["project"];
$projectid = get_project_id($projectname);

// If not a valid project we return
if($projectid == -1)
  {
  echo "Not a valid project";
  add_log('Not a valid project. projectname: ' . $projectname, 'global:submit.php');
  exit();
  }
  
// If the submission is asynchronous we store in the database
if($CDASH_ASYNCHRONOUS_SUBMISSION)
  {
  do_submit_asynchronous($fp, $projectid);
  }
else  
  {
  do_submit($fp, $projectid);
  }
?>
