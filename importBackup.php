<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
  Language:  PHP
  Date:      $Date: 2008-03-06 22:24:57 -0500 (Thu, 06 Mar 2008) $
  Version:   $Revision: 666 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("config.php");
include('login.php');

if($session_OK) 
  {
  include_once('common.php');
  include_once("ctestparser.php");

set_time_limit(0);

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

checkUserPolicy(@$_SESSION['cdash']['loginid'],0); // only admin
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<title>CDash - Import Backups</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Backups</menusubtitle>";
$xml .= "<backurl>user.php</backurl>";
$xml .= "</cdash>";

  
// If we should create the tables
@$Submit = $_POST["Submit"];
if($Submit)
{
  foreach(glob("$CDASH_BACKUP_DIRECTORY/*.xml", $nFlags) as $filename)
    {
    # split on path separator 
    $pathParts = split("[/\\.]", $filename);
    # split on cdash separator "_"
    $cdashParts = split("[_]", $pathParts[1]);
    $projectid = get_project_id($cdashParts[0]);
    if($projectid != -1)
      {
      $name = get_project_name($projectid);
      echo "Project [$name] import : $filename<br>";
      ob_flush(); 
      $handle = fopen($filename,"r");
      $contents = fread($handle,filesize($filename));
      $xml_array = parse_XML($contents);
      ctest_parse($xml_array,$projectid);
      }
    else
      {
      echo "Project id not found skipping file: $filename<br>";
      ob_flush();
      }
    }
  exit(0);
}

// Now doing the xslt transition
generate_XSLT($xml,"importBackup");

} // end session
?>
