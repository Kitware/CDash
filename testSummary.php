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
if(!isset($testName))
  {
  die('Error: no test name supplied in query string');
  }

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
$project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
if(mysql_num_rows($project)>0)
  {
  $project_array = mysql_fetch_array($project);
  $svnurl = $project_array["cvsurl"];
  $homeurl = $project_array["homeurl"];
  $bugurl = $project_array["bugtrackerurl"];   
  $projectname = $project_array["name"];  
  }
list ($previousdate, $currenttime, $nextdate) = get_dates($date,$project_array["nightlytime"]);
$logoid = getLogoID($projectid);

$xml = '<?xml version="1.0" encoding="utf-8"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .="<dashboard>
  <datetime>".date("D, d M Y",$currenttime)."</datetime>
  <date>".$date."</date>
  <svn>".$svnurl."</svn>
  <bugtracker>".$bugurl."</bugtracker> 
  <home>".$homeurl."</home>
  <projectid>".$projectid."</projectid> 
  <logoid>".$logoid."</logoid>
  <projectname>".$projectname."</projectname> 
  <previousdate>".$previousdate."</previousdate> 
  <nextdate>".$nextdate."</nextdate> 
  <testName>".$testName."</testName>
  </dashboard>
  ";
  
//get information about all the builds for the given date and project
$xml .= "<builds>\n";
$buildQuery = "SELECT id,name,stamp,siteid FROM build WHERE stamp RLIKE '^$date-' AND projectid = '$projectid'";
$buildResult = mysql_query($buildQuery);

$builds = array();
while($buildRow = mysql_fetch_array($buildResult))
  {
  $builds[$buildRow["id"]] =
    array("name" => $buildRow["name"],
          "stamp" => $buildRow["stamp"],
          "siteid" => $buildRow["siteid"]);
  }

//generate a big ugly SQL select statement and execute it
//this statement will give us information about each test that didn't pass
//for the given date and time
$firstTime = TRUE;
foreach($builds as $buildid => $buildData)
  {
  if($firstTime)
    {
    $query =
      "SELECT test.id,build2test.buildid,build2test.status,build2test.time,test.details,site.name 
						 FROM test,site,build2test WHERE ( (build2test.buildid='$buildid' AND test.id=build2test.testid AND site.id = '".$buildData["siteid"]."')";
    $firstTime = FALSE;
    }
  else
    {
    $query .= " OR (build2test.buildid='$buildid' AND test.id=build2test.testid AND site.id = '".$buildData["siteid"]."')";
    }
  }
$query .= ") AND test.name = '$testName' AND status != '' ORDER BY status";
$result = mysql_query($query);

$color = FALSE;
//now that we have the data we need, generate some XML
while($row = mysql_fetch_array($result))
  {
  $buildid = $row["buildid"];
  $xml .= "<build>\n";
  $xml .= add_XML_value("site", $row["name"]) . "\n";
  $xml .= add_XML_value("buildName", $builds[$buildid]["name"]) . "\n";
  $xml .= add_XML_value("buildStamp", $builds[$buildid]["stamp"]) . "\n";
  $xml .= add_XML_value("time", $row["time"]) . "\n";
  $xml .= add_XML_value("details", $row["details"]) . "\n";
  $buildLink = "viewTest.php?buildid=$buildid";
  $xml .= add_XML_value("buildLink", $buildLink) . "\n";
  $testid = $row["id"];
  $testLink = "testDetails.php?test=$testid&build=$buildid";
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
  switch($row["status"])
    {
    case "passed":
      $xml .= add_XML_value("status", "Passed") . "\n";
      //$numPassed++;
      break; 
    case "failed":
      $xml .= add_XML_value("status", "Failed") . "\n";
      //$numFailed++;
      break;
    case "notrun":
      $xml .= add_XML_value("status", "Not Run") . "\n";
      //$numNotRun++;
      break;
    }
  $xml .= "</build>\n";
  }
$xml .= "</builds>\n";
$xml .= "</cdash>\n";

// Now doing the xslt transition
generate_XSLT($xml,"testSummary");
?>
