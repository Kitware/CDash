<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: buildOverview.php 1161 2008-09-19 14:56:14Z jjomier $
  Language:  PHP
  Date:      $Date: 2008-03-06 22:24:57 -0500 (Thu, 06 Mar 2008) $
  Version:   $Revision: 666 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include("cdash/version.php");

if($session_OK) 
{
include_once('cdash/common.php');
include_once("cdash/ctestparser.php");

set_time_limit(0);

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

checkUserPolicy(@$_SESSION['cdash']['loginid'],0); // only admin
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= "<title>CDash - Import Backups</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Backups</menusubtitle>";
$xml .= "<backurl>manageBackup.php</backurl>";
$xml .= "</cdash>";

@$Submit = $_POST["Submit"];

@$filemask = $_POST["filemask"];
if ($filemask == '')
{
  $filemask = "*.xml";
}

if($Submit && $filemask)
  {
  $filelist = glob("$CDASH_BACKUP_DIRECTORY/$filemask");

  $i = 0;
  $n = count($filelist);

  foreach($filelist as $filename)
    {
    ++$i;
    $projectid = -1;

    # split on path separator
    $pathParts = split("[/\\]", $filename);

    # split on cdash separator "_"
    if(count($pathParts)>=1)
      {
      $cdashParts = split("[_]", $pathParts[count($pathParts)-1]);
      $projectid = get_project_id($cdashParts[0]);
      }

    //echo 'i: ' . print_r($i, true) . '<br/>';
    //echo 'filename: ' . print_r($filename, true) . '<br/>';
    //echo 'pathParts: ' . print_r($pathParts, true) . '<br/>';
    //echo 'cdashParts: ' . print_r($cdashParts, true) . '<br/>';
    //echo 'projectid: ' . print_r($projectid, true) . '<br/>';
    //echo '<br/>';

    if($projectid != -1)
      {
      $name = get_project_name($projectid);

      echo 'Project ['.$name.'] importing file ('.$i.'/'.$n.'): '.$filename.'<br/>';
      ob_flush();
      flush();

      $handle = fopen($filename,"r");
      ctest_parse($handle,$projectid);
      fclose($handle);
      }
    else
      {
      echo 'Project id not found - skipping file ('.$i.'/'.$n.'): '.$filename.'<br/>';
      ob_flush();
      flush();
      }
    }

  echo 'Import backup complete. '.$i.' files processed.<br/>';
  echo '<br/>';
  } // end submit

// Now doing the xslt transition
generate_XSLT($xml,"importBackup");

} // end session
?>
