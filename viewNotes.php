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
$noforcelogin = 1;
include("config.php");
include('login.php');
include("common.php");
include("version.php");

@$buildid = $_GET["buildid"];
@$date = $_GET["date"];

// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }
  
include("config.php");
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
  
$build_array = mysql_fetch_array(mysql_query("SELECT projectid FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];
checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);

if(!isset($date) || strlen($date)==0)
{ 
  $currenttime = time();
}
else
{
  $currenttime = mktime("23","59","0",substr($date,4,2),substr($date,6,2),substr($date,0,4));
}
    
$project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
if(mysql_num_rows($project)>0)
{
  $project_array = mysql_fetch_array($project);  
  $projectname = $project_array["name"];  
}

$previousdate = date("Ymd",$currenttime-24*3600); 
$nextdate = date("Ymd",$currenttime+24*3600);

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";

$xml .= get_cdash_dashboard_xml(get_project_name($projectid),$date);
  
// Build
$xml .= "<build>";
$build = mysql_query("SELECT * FROM build WHERE id='$buildid'");
$build_array = mysql_fetch_array($build); 
$siteid = $build_array["siteid"];
$site_array = mysql_fetch_array(mysql_query("SELECT name FROM site WHERE id='$siteid'"));
$xml .= add_XML_value("site",$site_array["name"]);
$xml .= add_XML_value("buildname",$build_array["name"]);
$xml .= add_XML_value("buildid",$build_array["id"]);
$xml .= "</build>";
  
  
$build2note = mysql_query("SELECT noteid,time FROM build2note WHERE buildid='$buildid'");
while($build2note_array = mysql_fetch_array($build2note))
  {
  $noteid = $build2note_array["noteid"];
  $note_array = mysql_fetch_array(mysql_query("SELECT * FROM note WHERE id='$noteid'"));
  $xml .= "<note>";
  $xml .= add_XML_value("name",$note_array["name"]);
  $xml .= add_XML_value("text",$note_array["text"]);
  $xml .= add_XML_value("time",$build2note_array["time"]);
  $xml .= "</note>";
  $text = $note_array["text"];
  $name = $note_array["name"];
  }

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewNotes");
?>
