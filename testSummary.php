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
$noforcelogin = 1;
include("config.php");
include('login.php');
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
  $projectname = $project_array["name"];  
  }
  
checkUserPolicy(@$_SESSION['cdash']['loginid'],$project_array["id"]);

$xml = '<?xml version="1.0" encoding="utf-8"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);
$xml .="<testName>".$testName."</testName>";
  
//get information about all the builds for the given date and project
$xml .= "<builds>\n";

$query = "SELECT build.id,build.name,build.stamp,build2test.status,build2test.time,test.id AS testid,site.name AS sitename 
          FROM build,build2test,test,site WHERE build.stamp RLIKE '^$date-'           
          AND build.projectid = '$projectid' AND build2test.buildid=build.id 
     AND test.id=build2test.testid AND test.name='$testName' 
     AND site.id=build.siteid
     ORDER BY build2test.status";

$result = mysql_query($query);

$color = FALSE;
//now that we have the data we need, generate some XML
while($row = mysql_fetch_array($result))
  {
  $buildid = $row["id"];
  $xml .= "<build>\n";
 
 $xml .= add_XML_value("site", $row["sitename"]) . "\n";
  $xml .= add_XML_value("buildName", $row["name"]) . "\n";
  $xml .= add_XML_value("buildStamp", $row["stamp"]) . "\n";
  $xml .= add_XML_value("time", $row["time"]) . "\n";
  //$xml .= add_XML_value("details", $row["details"]) . "\n";
  $buildLink = "viewTest.php?buildid=$buildid";
  $xml .= add_XML_value("buildLink", $buildLink) . "\n";
  $testid = $row["testid"];
  $testLink = "testDetails.php?test=$testid&build=$buildid";
  $xml .= add_XML_value("testLink", $testLink) . "\n";
  if($color)
    {
    $xml .= add_XML_value("class", "treven") . "\n";
    }
  else
    {
    $xml .= add_XML_value("class", "trodd") . "\n";
    }
  $color = !$color;
  switch($row["status"])
    {
    case "passed":
      $xml .= add_XML_value("status", "Passed") . "\n";
      $xml .= add_XML_value("statusclass", "normal") . "\n";
      break; 
    case "failed":
      $xml .= add_XML_value("status", "Failed") . "\n";
      $xml .= add_XML_value("statusclass", "warning") . "\n";
      break;
    case "notrun":
      $xml .= add_XML_value("status", "Not Run") . "\n";
   $xml .= add_XML_value("statusclass", "error") . "\n";
      break;
    }
  $xml .= "</build>\n";
  }
$xml .= "</builds>\n";
$xml .= "</cdash>\n";

// Now doing the xslt transition
generate_XSLT($xml,"testSummary");
?>
