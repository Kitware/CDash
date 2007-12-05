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
* testDetails.php shows more detailed information for a particular test that
* was run.  This includes test output and image comparison information
*/

include("config.php");
include("common.php");

$testid = $_GET["test"];
if(!isset($testid))
  {
  die('Error: no test id supplied in query string');
  }

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

$testRow = mysql_fetch_array(mysql_query("SELECT * FROM build2test,test WHERE build2test.testid = '$testid' AND build2test.testid=test.id"));
$buildid = $testRow["buildid"];

$buildRow = mysql_fetch_array(mysql_query("SELECT * FROM build WHERE id = '$buildid'"));
$projectid = $buildRow["projectid"];
$siteid = $buildRow["siteid"];

$project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
if(mysql_num_rows($project)>0)
  {
  $project_array = mysql_fetch_array($project);
  $svnurl = $project_array["cvsurl"];
  $homeurl = $project_array["homeurl"];
  $bugurl = $project_array["bugtrackerurl"];   
  $projectname = $project_array["name"];  
  }

$projectRow = mysql_fetch_array(mysql_query("SELECT name FROM project WHERE id = '$projectid'"));
$projectname = $projectRow["name"];

$siteQuery = "SELECT name FROM site WHERE id = '$siteid'";
$siteResult = mysql_query($siteQuery);
$siteRow = mysql_fetch_array(mysql_query("SELECT name FROM site WHERE id = '$siteid'"));

$date = date("Ymd", strtotime($buildRow["starttime"]));
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
  </dashboard>
  ";
  
$testName = $testRow["name"];
$summaryLink = "testSummary.php?project=$projectid&name=$testName&date=$date";


$xml .= "<test>\n";
$xml .= add_XML_value("build", $buildRow["name"]) . "\n";
$xml .= add_XML_value("site", $siteRow["name"]) . "\n";
$xml .= add_XML_value("test", $testName) . "\n";
$xml .= add_XML_value("time", $testRow["time"]) . "\n";
$xml .= add_XML_value("command", $testRow["command"]) . "\n";
$xml .= add_XML_value("details", $testRow["details"]) . "\n";
$xml .= add_XML_value("output", $testRow["output"]) . "\n";
$xml .= add_XML_value("summaryLink", $summaryLink) . "\n";
switch($testRow["status"])
  {
  case "passed":
    $xml .= add_XML_value("status", "Passed") . "\n";
    $xml .= add_XML_value("statusColor", "#00aa00") . "\n";
    break;
  case "failed":
    $xml .= add_XML_value("status", "Failed") . "\n";
    $xml .= add_XML_value("statusColor", "#aa0000") . "\n";
    break;
  case "notrun":
    $xml .= add_XML_value("status", "Not Run") . "\n";
    $xml .= add_XML_value("statusColor", "#ffcc66") . "\n";
    break;
  }

//get any images associated with this test
$xml .= "<images>\n";
$query = "SELECT * FROM image2test WHERE testid = '$testid'";
$result = mysql_query($query);
while($row = mysql_fetch_array($result))
  {
  $xml .= "<image>\n";
  $xml .= add_XML_value("imgid", $row["imgid"]) . "\n";
  $xml .= add_XML_value("role", $row["role"]) . "\n";
  
  $xml .= "</image>\n";
  }
$xml .= "</images>\n";
$xml .= "</test>\n";


$xml .= "</cdash>\n";

// Now doing the xslt transition
generate_XSLT($xml,"testDetails");
?>
