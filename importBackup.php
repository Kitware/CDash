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
$xml = begin_XML_for_XSLT();
$xml .= "<title>CDash - Import Backups</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Backups</menusubtitle>";
$xml .= "<backurl>manageBackup.php</backurl>";
$alert = "";

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

  add_log(
    "before loop n=".$n,
    "importBackup.php",
    LOG_INFO);

  foreach($filelist as $filename)
    {
    ++$i;
    $projectid = -1;

    add_log(
      "looping i=".$i." filename=".$filename,
      "importBackup.php",
      LOG_INFO);

    # split on path separator
    $pathParts = split("[/\\]", $filename);

    # split on cdash separator "_"
    if(count($pathParts)>=1)
      {
      $cdashParts = split("[_]", $pathParts[count($pathParts)-1]);
      $projectid = get_project_id($cdashParts[0]);
      }

    if($projectid != -1)
      {
      $name = get_project_name($projectid);
      $handle = fopen($filename,"r");
      if($handle)
        {
        ctest_parse($handle,$projectid);
        fclose($handle);
        unset($handle);
        }
      else
        {
        add_log(
          "could not open file filename=".$filename,
          "importBackup.php",
          LOG_ERR);
        }
      }
    else
      {
      add_log(
        "could not determine projectid from filename=".$filename,
        "importBackup.php",
        LOG_ERR);
      }
    }

  add_log(
    "after loop n=".$n,
    "importBackup.php",
    LOG_INFO);

  $alert = 'Import backup complete. '.$i.' files processed.';
  $xml .= add_XML_value("alert",$alert);
  } // end submit

// Now doing the xslt transition
$xml .= "</cdash>";
generate_XSLT($xml,"importBackup");

} // end session
?>
