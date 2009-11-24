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
require_once("filterdataFunctions.php");
include_once("models/build.php");

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

$project = pdo_query("SELECT name,showtesttime,testtimemaxstatus,nightlytime,displaylabels FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);
  $projectname = $project_array["name"];  
  $projectshowtesttime = $project_array["showtesttime"];  
  $testtimemaxstatus = $project_array["testtimemaxstatus"];
  }

$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$starttime = $build_array["starttime"];

$date = get_dashboard_date_from_build_starttime($starttime, $project_array["nightlytime"]);
  
$xml = '<?xml version="1.0" encoding="utf-8"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";

$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);

// Menu
$xml .= "<menu>";

$onlypassed = 0;
$onlyfailed = 0;
$onlytimestatus = 0;
$onlynotrun = 0;
$onlydelta = 0;
$extraquery = "";

if(isset($_GET["onlypassed"]))
  {
  $onlypassed = 1;
  $extraquery = "&onlypassed";
  }
else if(isset($_GET["onlyfailed"]))
  {
  $onlyfailed = 1;
  $extraquery = "&onlyfailed";
  }
else if(isset($_GET["onlytimestatus"]))
  {
  $onlytimestatus = 1;
  $extraquery = "&onlytimestatus";
  }
else if(isset($_GET["onlynotrun"]))
  {
  $onlynotrun = 1;
  $extraquery = "&onlynotrun";
  }
else if(isset($_GET["onlydelta"]))
  {
  $onlydelta = 1;
  $extraquery = "&onlydelta";
  }

$nightlytime = get_project_property($projectname,"nightlytime");
$xml .= add_XML_value("back","index.php?project=".urlencode($projectname)."&date=".get_dashboard_date_from_build_starttime($build_array["starttime"],$nightlytime));
$previousbuildid = get_previous_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($previousbuildid>0)
  {
  $xml .= add_XML_value("previous","viewTest.php?buildid=".$previousbuildid.$extraquery);
  }
else
  {
  $xml .= add_XML_value("noprevious","1");
  }  
$xml .= add_XML_value("current","viewTest.php?buildid=".get_last_buildid($projectid,$siteid,$buildtype,$buildname,$starttime).$extraquery);  
$nextbuildid = get_next_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($nextbuildid>0)
  {
  $xml .= add_XML_value("next","viewTest.php?buildid=".$nextbuildid.$extraquery);
  }  
else
  {
  $xml .= add_XML_value("nonext","1");
  }
$xml .= "</menu>";

$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$xml .= "<build>\n";
$xml .= add_XML_value("displaylabels",$project_array["displaylabels"]);
$xml .= add_XML_value("site",$site_array["name"]);
$xml .= add_XML_value("siteid",$siteid);
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


$displaydetails = 1;
$status = '';
$order = 't.name';

if($onlypassed)
  {
  $displaydetails = 0;
  $status = "AND bt.status='passed'";
  }
else if($onlyfailed)
  {
  $status = "AND bt.status='failed'";
  }
else if($onlynotrun)
  {
  $displaydetails = 0;
  $status = "AND bt.status='notrun'";
  }
else if($onlytimestatus)
  {
  $status = "AND bt.timestatus>='$testtimemaxstatus'";
  }
else
  {
  $order = 'bt.status,bt.timestatus DESC,t.name';
  }

$xml .= add_XML_value("displaydetails", $displaydetails);
$xml .= add_XML_value("onlypassed", $onlypassed);
$xml .= add_XML_value("onlyfailed", $onlyfailed);
$xml .= add_XML_value("onlytimestatus", $onlytimestatus);
$xml .= add_XML_value("onlynotrun", $onlynotrun);


// Filters:
//
$filterdata = get_filterdata_from_request();
$filter_sql = $filterdata['sql'];
$xml .= $filterdata['xml'];

$build = new Build();
$build->Id = $buildid;
$build->FillFromId($buildid);
$previousBuildId = $build->GetPreviousBuildId();
 
$sql = "SELECT bt.status,bt.timestatus,t.id,bt.time,t.details,t.name " .
       "FROM test as t,build2test as bt " .
       "WHERE bt.buildid='$buildid' AND t.id=bt.testid " . $status . " " .
       $filter_sql . " " .
       "ORDER BY " . $order;

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
  $currentStatus = $row["status"];
  $previousStatus;
  $newClass = '';
  $newText = '';
  $testName = $row["name"];
  
  if($previousBuildId)
    {
    //fetch the previous test status
    $testName = pdo_real_escape_string($testName);
    $sql = "SELECT bt.status FROM test as t,build2test as bt " .
       "WHERE bt.buildid='$previousBuildId' AND t.id=bt.testid ".
       "AND t.name='$testName'";

    $res = pdo_query($sql);
    if($arr = pdo_fetch_array($res))
      {
      $previousStatus = $arr["status"];
      }
    }
  
  if(isset($previousStatus) && $previousStatus != $currentStatus)
    {
    $newClass = 'New';
    $newText = ' (New)';
    }
  else if($onlydelta)
    {
    continue;
    }
  
  $xml .= "<test>\n";
  $xml .= add_XML_value("name", $testName);
  $xml .= add_XML_value("execTime", $row["time"]);
  $xml .= add_XML_value("details", $row["details"]); 
  $testdate = get_dashboard_date_from_build_starttime($build_array["starttime"],$nightlytime);
  $summaryLink = "testSummary.php?project=$projectid&name=$testName&date=$testdate";
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
      $xml .= add_XML_value("timestatusclass", "error");
      }
    } // end projectshowtesttime
  
  switch($currentStatus)
    {
    case "passed":
      $xml .= add_XML_value("status", "Passed$newText");
      $xml .= add_XML_value("statusclass", "normal$newClass");
      $numPassed++;
      break;
    case "failed":
      $xml .= add_XML_value("status", "Failed$newText");
      $xml .= add_XML_value("statusclass", "error$newClass");
      $numFailed++;
      break;
    case "notrun":
      $xml .= add_XML_value("status", "Not Run$newText");
      $xml .= add_XML_value("statusclass", "warning$newClass");
      $numNotRun++;
      break;
    }
  
  if($row["timestatus"] >= $testtimemaxstatus)
    {
    $numTimeFailed++;   
    }

  $testid = $row['id'];

  $xml .= get_labels_xml_from_query_results(
    "SELECT text FROM label, label2test WHERE ".
    "label.id=label2test.labelid AND ".
    "label2test.testid='$testid' AND ".
    "label2test.buildid='$buildid' ".
    "ORDER BY text ASC"
    );

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
