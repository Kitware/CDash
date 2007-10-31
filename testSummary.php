<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/

/*
* testSummary.php displays a list of all builds that performed a given test
* on a specific day.  It also displays information (success, execution time)
* about each copy of the test that was run.
*/

include("config.php");
include("common.php");

$date = $_GET["date"];
if(!isset($date) || strlen($date)==0)
  { 
  die('Error: no date supplied in query string');
  }
$projectid = $_GET["project"];
if(!isset($projectid))
  {
  die('Error: no project supplied in query string');
  }
$testName = $_GET["name"];
if(!isset($projectid))
  {
  die('Error: no test name supplied in query string');
  }

$currenttime = mktime("23","59","0",substr($date,4,2),substr($date,6,2),substr($date,0,4));
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
$project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
if(mysql_num_rows($project)>0)
  {
  $project_array = mysql_fetch_array($project);
  $svnurl = $project_array["cvsurl"];
  $homeurl = $project_array["homeurl"];
  $bugurl = $project_array["bugtrackerurl"];			
  $projectname	= $project_array["name"];		
  }
$previousdate = date("Ymd",$currenttime-24*3600);	
$nextdate = date("Ymd",$currenttime+24*3600);

$xml = '<?xml version="1.0" encoding="utf-8"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .="<dashboard>
  <datetime>".date("D, d M Y H:i:s",$currenttime)."</datetime>
  <date>".date("l, F d Y",$currenttime)."</date>
  <svn>".$svnurl."</svn>
  <bugtracker>".$bugurl."</bugtracker>	
  <home>".$homeurl."</home>
  <projectid>".$projectid."</projectid>	
  <projectname>".$projectname."</projectname>	
  <previousdate>".$previousdate."</previousdate>	
  <nextdate>".$nextdate."</nextdate>	
  <testName>".$testName."</testName>
  </dashboard>
  ";
		
// Here's where we start gathering information relevant to the task at hand
/*
$dateStart = mktime("0","0","0",substr($date,4,2),substr($date,6,2),substr($date,0,4));
$dateEnd = mktime("23","59","59",substr($date,4,2),substr($date,6,2),substr($date,0,4));
*/
$xml .= "<builds>\n";
$buildQuery = "SELECT * FROM build WHERE starttime >  '$date' AND starttime < '$nextdate' AND projectid = '$projectid'";
$buildResult = mysql_query($buildQuery);
$color = FALSE;
while($buildRow = mysql_fetch_array($buildResult))
  {
  $buildid = $buildRow["id"];
  $siteid = $buildRow["siteid"];
  //I bet there's a smarter way to do all these queries using an sql join or
  //some such thing...
  $testQuery = "SELECT id,status,time,details FROM test WHERE buildid = '$buildid' AND name = '$testName'";
  $testResult = mysql_query($testQuery);
  $testRow = mysql_fetch_array($testResult);
  $siteQuery = "SELECT name FROM site WHERE id = '$siteid'";
  $siteResult = mysql_query($siteQuery);
  $siteRow = mysql_fetch_array($siteResult);
  $xml .= "<build>\n";
  $xml .= add_XML_value("site", $siteRow["name"]) . "\n";
  $xml .= add_XML_value("buildName", $buildRow["name"]) . "\n";
  $xml .= add_XML_value("buildStamp", $buildRow["stamp"]) . "\n";
  $xml .= add_XML_value("time", $testRow["time"]) . "\n";
  $xml .= add_XML_value("details", $testRow["details"]) . "\n";
  $buildLink = "viewTest.php?buildid=$buildid";
  $xml .= add_XML_value("buildLink", $buildLink) . "\n";
  $testid = $testRow["id"];
  $testLink = "testDetails.php?testid=$testid";
  $xml .= add_XML_value("testLink", $testLink) . "\n";
  if($color)
    {
    $xml .= add_XML_value("class", "tr-even") . "\n";
    }
  else
    {
    $xml .= add_XML_value("class", "tr-odd") . "\n";
    }
  $color = !$color;
  switch($testRow["status"])
    {
    case "passed":
      $xml .= add_XML_value("status", "Passed") . "\n";
      $numPassed++;
      break; 
    case "failed":
      $xml .= add_XML_value("status", "Failed") . "\n";
      $numFailed++;
      break;
    case "notrun":
      $xml .= add_XML_value("status", "Not Run") . "\n";
      $numNotRun++;
      break;
    }
  $xml .= "</build>\n";
  }
$xml .= "</builds>\n";
$xml .= "</cdash>\n";

// Now doing the xslt transition
generate_XSLT($xml,"testSummary");
?>
