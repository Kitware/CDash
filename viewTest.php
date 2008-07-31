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
require_once("pdo.php");
include('login.php');
include_once("common.php");
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
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);
  
$build_array = pdo_fetch_array(pdo_query("SELECT * FROM build WHERE id='$buildid'"));  
$projectid = $build_array["projectid"];
checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);

if(!isset($date) || strlen($date)==0)
  { 
  $date = date("Ymd", strtotime($build_array["starttime"]));
  }
    
$project = pdo_query("SELECT name,showtesttime,testtimemaxstatus FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);
  $projectname = $project_array["name"];  
  $projectshowtesttime = $project_array["showtesttime"];  
  $testtimemaxstatus = $project_array["testtimemaxstatus"];
  }

$xml = '<?xml version="1.0" encoding="utf-8"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";

$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);
  
$siteid = $build_array["siteid"];
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$xml .= "<build>\n";
$xml .= add_XML_value("site",$site_array["name"]);
$xml .= add_XML_value("buildname",$build_array["name"]);
$xml .= add_XML_value("buildid",$build_array["id"]);
$xml .= add_XML_value("testtime", $build_array["endtime"]);

// Find the OS and compiler information
$buildinformation = pdo_query("SELECT * FROM buildinformation WHERE buildid='$buildid'");
if(pdo_num_rows($buildinformation)>0)
  {
  $buildinformation_array = pdo_fetch_array($buildinformation);
  if($buildinformation_array["osname"]!="")
    {
    $xml .= add_XML_value("osname",$buildinformation_array["osname"]);
    }
  if($buildinformation_array["osplatform"]!="")
    {
    $xml .= add_XML_value("osplatform",$buildinformation_array["osplatform"]);
    }
  if($buildinformation_array["osrelease"]!="")
    {
    $xml .= add_XML_value("osrelease",$buildinformation_array["osrelease"]);
    }
  if($buildinformation_array["osversion"]!="")
    {
    $xml .= add_XML_value("osversion",$buildinformation_array["osversion"]);
    }
  if($buildinformation_array["compilername"]!="")
    {
    $xml .= add_XML_value("compilername",$buildinformation_array["compilername"]);
    }
  if($buildinformation_array["compilerversion"]!="")
    {
    $xml .= add_XML_value("compilerversion",$buildinformation_array["compilerversion"]);
    }
  }
$xml .= "</build>\n";

$xml .= "<project>";
$xml .= add_XML_value("showtesttime", $projectshowtesttime);
$xml .= "</project>";

if(isset($_GET["onlypassed"]))
  {
  $xml .= "<onlypassed>1</onlypassed>";
  $sql = "SELECT bt.status,bt.timestatus,t.id,bt.time,t.details,t.name FROM test as t,build2test as bt 
           WHERE bt.buildid='$buildid' AND bt.status='passed' AND t.id=bt.testid ORDER BY t.name"; 
  }
else if(isset($_GET["onlyfailed"]))
  {
  $xml .= "<onlyfailed>1</onlyfailed>";
  $sql = "SELECT bt.status,bt.timestatus,t.id,bt.time,t.details,t.name FROM test as t,build2test as bt 
         WHERE bt.buildid='$buildid' AND bt.status!='passed' AND t.id=bt.testid ORDER BY t.name";
  }
else if(isset($_GET["onlytimestatus"]))
  {
  $xml .= "<onlytimestatus>1</onlytimestatus>";
  $sql = "SELECT bt.status,bt.timestatus,t.id,bt.time,t.details,t.name FROM test as t,build2test as bt 
            WHERE bt.buildid='$buildid' AND bt.timestatus>='$testtimemaxstatus' AND t.id=bt.testid ORDER BY t.name";
  }
else
  {
  $xml .= "<onlypassed>0</onlypassed>";
  $xml .= "<onlyfailed>0</onlyfailed>";
  $xml .= "<onlytimestatus>0</onlytimestatus>";
  $sql = "SELECT bt.status,bt.timestatus,t.id,bt.time,t.details,t.name FROM test as t,build2test as bt 
         WHERE bt.buildid='$buildid' AND t.id=bt.testid ORDER BY bt.status,bt.timestatus DESC,t.name";
  }
$result = pdo_query($sql);


$numPassed = 0;
$numFailed = 0;
$numNotRun = 0;
$numTimeFailed = 0;

// Gather test info
$xml .= "<tests>\n";

// Find the time to run all the tests
$time_array = pdo_fetch_array(pdo_query("SELECT SUM(time) FROM build2test WHERE buildid='$buildid'"));
$time = $time_array[0];
$xml .= add_XML_value("totaltime", get_formated_time($time));

while($row = pdo_fetch_array($result))
  {
  $xml .= "<test>\n";
  $testName = $row["name"];
  $xml .= add_XML_value("name", $testName);
  $xml .= add_XML_value("execTime", $row["time"]);
  $xml .= add_XML_value("details", $row["details"]); 
  $summaryLink = "testSummary.php?project=$projectid&name=$testName&date=$date";
  $xml .= add_XML_value("summaryLink", $summaryLink);
  $testid = $row["id"]; 
  $detailsLink = "testDetails.php?test=$testid&build=$buildid";
  $xml .= add_XML_value("detailsLink", $detailsLink);
  
  if($projectshowtesttime)
    {
    if($row["timestatus"] < $testtimemaxstatus)
      {
      $xml .= add_XML_value("timestatus", "Passed");
      $xml .= add_XML_value("timestatusclass", "normal");
      }
    else
      {    
      $xml .= add_XML_value("timestatus", "Failed");
      $xml .= add_XML_value("timestatusclass", "warning");
      }
    } // end projectshowtesttime
    
  switch($row["status"])
    {
    case "passed":
      $xml .= add_XML_value("status", "Passed");
      $xml .= add_XML_value("statusclass", "normal");
      $numPassed++;   
      break; 
    case "failed":
      $xml .= add_XML_value("status", "Failed");
      $xml .= add_XML_value("statusclass", "warning");   
      $numFailed++;   
      break;
    case "notrun":
      $xml .= add_XML_value("status", "Not Run");
      $xml .= add_XML_value("statusclass", "error");
      $numNotRun++;
      break;
    }
  
  if($row["timestatus"] >= $testtimemaxstatus)
    {
    $numTimeFailed++;   
    }
    
  $xml .= "</test>\n";
  }
$xml .= "</tests>\n";
$xml .= add_XML_value("numPassed", $numPassed);
$xml .= add_XML_value("numFailed", $numFailed);
$xml .= add_XML_value("numNotRun", $numNotRun);
$xml .= add_XML_value("numTimeFailed", $numTimeFailed);

$end = microtime_float();
$xml .= "<generationtime>".round($end-$start,3)."</generationtime>";
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewTest");
?>
