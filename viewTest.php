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
  
$start = microtime_float();
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
  
$build_array = mysql_fetch_array(mysql_query("SELECT * FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];
checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);

if(!isset($date) || strlen($date)==0)
  { 
  $date = date("Ymd", strtotime($build_array["starttime"]));
  }
    
$project = mysql_query("SELECT name,showtesttime FROM project WHERE id='$projectid'");
if(mysql_num_rows($project)>0)
  {
  $project_array = mysql_fetch_array($project);
  $projectname = $project_array["name"];  
  $projectshowtesttime = $project_array["showtesttime"];  
  }

$xml = '<?xml version="1.0" encoding="utf-8"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";

$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);
  
#$siteid = $build_array["siteid"];
#$site_array =
#  mysql_fetch_array(mysql_query("SELECT * FROM build WHERE id='$siteid'"));
$siteid = $build_array["siteid"];
$site_array =
  mysql_fetch_array(mysql_query("SELECT name FROM site WHERE id='$siteid'"));
$xml .= "<build>\n";
$xml .= add_XML_value("site",$site_array["name"]) . "\n";
$xml .= add_XML_value("buildname",$build_array["name"]) . "\n";
$xml .= add_XML_value("buildid",$build_array["id"]) . "\n";
$xml .= add_XML_value("testtime", $build_array["endtime"]) . "\n";
$xml .= "</build>\n";

$xml .= "<project>";
$xml .= add_XML_value("showtesttime", $projectshowtesttime) . "\n";
$xml .= "</project>";


// Gather test info
$xml .= "<tests>\n";

if(isset($_GET["onlypassed"]))
  {
  if($projectshowtesttime)
    {
    $sql = "SELECT bt.status,bt.timestatus,t.id,bt.time,t.details,t.name FROM test as t,build2test as bt 
         WHERE bt.buildid='$buildid' AND bt.status='passed' AND bt.timestatus=0 AND t.id=bt.testid ORDER BY t.name";
    }
  else
    {
    $sql = "SELECT bt.status,bt.timestatus,t.id,bt.time,t.details,t.name FROM test as t,build2test as bt 
            WHERE bt.buildid='$buildid' AND bt.status='passed' AND t.id=bt.testid ORDER BY t.name";
    }  
  }
else if(isset($_GET["onlyfailed"]))
  {
  if($projectshowtesttime)
    {
    $sql = "SELECT bt.status,bt.timestatus,t.id,bt.time,t.details,t.name FROM test as t,build2test as bt 
         WHERE bt.buildid='$buildid' AND (bt.status!='passed' OR bt.timestatus!=0) AND t.id=bt.testid ORDER BY t.name";
    }
  else
    {
    $sql = "SELECT bt.status,bt.timestatus,t.id,bt.time,t.details,t.name FROM test as t,build2test as bt 
         WHERE bt.buildid='$buildid' AND bt.status!='passed' AND t.id=bt.testid ORDER BY t.name";
    }
  }
else
  {
  $sql = "SELECT bt.status,bt.timestatus,t.id,bt.time,t.details,t.name FROM test as t,build2test as bt 
         WHERE bt.buildid='$buildid' AND t.id=bt.testid ORDER BY bt.status,bt.timestatus DESC,t.name";
  }
$result = mysql_query($sql);

$numPassed = 0;
$numFailed = 0;
$numNotRun = 0;
$color = FALSE;
while($row = mysql_fetch_array($result))
  {
  $xml .= "<test>\n";
  $testName = $row["name"];
  $xml .= add_XML_value("name", $testName) . "\n";
  $xml .= add_XML_value("execTime", $row["time"]) . "\n";
  $xml .= add_XML_value("details", $row["details"]) . "\n"; 
  $summaryLink = "testSummary.php?project=$projectid&name=$testName&date=$date";
  $xml .= add_XML_value("summaryLink", $summaryLink) . "\n";
  $testid = $row["id"]; 
  $detailsLink = "testDetails.php?test=$testid&build=$buildid";
  $xml .= add_XML_value("detailsLink", $detailsLink) . "\n";
  
  switch($row["timestatus"])
    {
    case 0:
      $xml .= add_XML_value("timestatus", "Passed") . "\n";
      $xml .= add_XML_value("timestatusclass", "normal") . "\n";
      $numPassed++;
      break; 
    case 1:
      $xml .= add_XML_value("timestatus", "Failed") . "\n";
      $xml .= add_XML_value("timestatusclass", "warning") . "\n";
      $numFailed++;
      break;
    }
    
  switch($row["status"])
    {
    case "passed":
      $xml .= add_XML_value("status", "Passed") . "\n";
      $xml .= add_XML_value("statusclass", "normal") . "\n";
      $numPassed++;
      break; 
    case "failed":
      $xml .= add_XML_value("status", "Failed") . "\n";
      $xml .= add_XML_value("statusclass", "warning") . "\n";
      $numFailed++;
      break;
    case "notrun":
      $xml .= add_XML_value("status", "Not Run") . "\n";
      $xml .= add_XML_value("statusclass", "error") . "\n";
      $numNotRun++;
      break;
    }
  $xml .= "</test>\n";
  }
$xml .= "</tests>\n";
$xml .= add_XML_value("numPassed", $numPassed) . "\n";
$xml .= add_XML_value("numFailed", $numFailed) . "\n";
$xml .= add_XML_value("numNotRun", $numNotRun) . "\n";

$end = microtime_float();
$xml .= "<generationtime>".round($end-$start,3)."</generationtime>";
$xml .= "</cdash>\n";

// Now doing the xslt transition
generate_XSLT($xml,"viewTest");
?>
