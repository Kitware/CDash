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
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include("cdash/version.php");

@$buildid = $_GET["buildid"];
if ($buildid != NULL)
  {
  $buildid = pdo_real_escape_numeric($buildid);
  }
@$date = $_GET["date"];
if ($date != NULL)
  {
  $date = htmlspecialchars(pdo_real_escape_string($date));
  }

// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }
 
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$build_array = pdo_fetch_array(pdo_query("SELECT * FROM build WHERE id='$buildid'"));
$projectid = $build_array["projectid"];
checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);

$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$starttime = $build_array["starttime"];

$project_array = pdo_fetch_array(pdo_query("SELECT * FROM project WHERE id='$projectid'"));
$projectname = $project_array["name"];

$xml = begin_XML_for_XSLT();
$xml .= "<title>CDash : ".$projectname."</title>";

$date = get_dashboard_date_from_build_starttime($build_array["starttime"],$project_array["nightlytime"]);
$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);

// Menu
$xml .= "<menu>";
$xml .= add_XML_value("back","index.php?project=".urlencode($projectname)."&date=".$date);
$previousbuildid = get_previous_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($previousbuildid>0)
  {
  $xml .= add_XML_value("previous","viewNotes.php?buildid=".$previousbuildid);
  }
else
  {
  $xml .= add_XML_value("noprevious","1");
  }
$xml .= add_XML_value("current","viewNotes.php?buildid=".get_last_buildid($projectid,$siteid,$buildtype,$buildname,$starttime));
$nextbuildid = get_next_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($nextbuildid>0)
  {
  $xml .= add_XML_value("next","viewNotes.php?buildid=".$nextbuildid);
  }
else
  {
  $xml .= add_XML_value("nonext","1");
  }
$xml .= "</menu>";

// Build
$xml .= "<build>";

$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$xml .= add_XML_value("site",$site_array["name"]);
$xml .= add_XML_value("siteid",$siteid);
$xml .= add_XML_value("buildname",$buildname);
$xml .= add_XML_value("buildid",$buildid);
$xml .= add_XML_value("stamp",$build_array["stamp"]);
$xml .= "</build>";
  
  
$build2note = pdo_query("SELECT noteid,time FROM build2note WHERE buildid='$buildid'");
while($build2note_array = pdo_fetch_array($build2note))
  {
  $noteid = $build2note_array["noteid"];
  $note_array = pdo_fetch_array(pdo_query("SELECT * FROM note WHERE id='$noteid'"));
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
