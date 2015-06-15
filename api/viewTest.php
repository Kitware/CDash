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
set_include_path(__DIR__.'/..');
$noforcelogin = 1;
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include("cdash/version.php");
require_once("filterdataFunctions.php");
include_once("models/build.php");

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

$response = array();

// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  $response['error'] = "Not a valid buildid!";
  echo json_encode($response);
  return;
  }

$start = microtime_float();
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$build_array = pdo_fetch_array(pdo_query("SELECT * FROM build WHERE id='$buildid'"));
$projectid = $build_array["projectid"];
if(!isset($projectid) || $projectid==0)
  {
  $response['error'] = "This build doesn't exist. Maybe it has been deleted.";
  echo json_encode($response);
  return;
  }

if (!checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid, 1))
  {
  $response['requirelogin'] = 1;
  echo json_encode($response);
  return;
  }

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


$response = begin_JSON_response();
$response['title'] = "CDash : $projectname";
get_dashboard_JSON_by_name($projectname, $date, $response);

// Menu
$menu = array();

$onlypassed = 0;
$onlyfailed = 0;
$onlytimestatus = 0;
$onlynotrun = 0;
$onlydelta = 0;
$extraquery = "";
$display = "";

if(isset($_GET["onlypassed"]))
  {
  $onlypassed = 1;
  $extraquery = "&onlypassed";
  $display = "onlypassed";
  }
else if(isset($_GET["onlyfailed"]))
  {
  $onlyfailed = 1;
  $extraquery = "&onlyfailed";
  $display = "onlyfailed";
  }
else if(isset($_GET["onlytimestatus"]))
  {
  $onlytimestatus = 1;
  $extraquery = "&onlytimestatus";
  $display = "onlytimestatus";
  }
else if(isset($_GET["onlynotrun"]))
  {
  $onlynotrun = 1;
  $extraquery = "&onlynotrun";
  $display = "onlynotrun";
  }
else if(isset($_GET["onlydelta"])) // new test that are showing up for this category
  {
  $onlydelta = 1;
  $extraquery = "&onlydelta";
  $display = "onlydelta";
  }
else
  {
  $display = "all";
  }


$nightlytime = get_project_property($projectname,"nightlytime");
$menu['back'] = "index.php?project=".urlencode($projectname)."&date=".get_dashboard_date_from_build_starttime($build_array["starttime"],$nightlytime);

$n = 4;
$previousbuildids = get_previous_buildid($projectid,$siteid,$buildtype,$buildname,$starttime, $n);
$previous_buildids_str = "";
if(count($previousbuildids) > 0)
  {
  @$previousbuildid = end(array_values($previousbuildids));
  $menu['previous'] = "viewTest.php?buildid=$previousbuildid$extraquery";

  if(count($previousbuildids) > 1)
    {
    $previous_buildids_str = implode (", ", $previousbuildids);
    }
  }
else
  {
  $menu['noprevious'] = "1";
  }
$menu['current'] = "viewTest.php?buildid=".get_last_buildid($projectid,$siteid,$buildtype,$buildname,$starttime).$extraquery;
$nextbuildid = get_next_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($nextbuildid>0)
  {
  $menu['next'] = "viewTest.php?buildid=".$nextbuildid.$extraquery;
  }
else
  {
  $menu['nonext'] = "1";
  }
$response['menu'] = $menu;

$build = array();
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$build['displaylabels'] = $project_array['displaylabels'];
$build['site'] = $site_array['name'];
$build['siteid'] = $siteid;
$build['buildname'] = $build_array['name'];
$build['buildid'] = $build_array['id'];
$build['testtime'] = $build_array['endtime'];

// Find the OS and compiler information
$buildinformation = pdo_query("SELECT * FROM buildinformation WHERE buildid='$buildid'");
if(pdo_num_rows($buildinformation)>0)
  {
  $buildinformation_array = pdo_fetch_array($buildinformation);
  if($buildinformation_array["osname"]!="")
    {
    $build['osname'] = $buildinformation_array["osname"];
    }
  if($buildinformation_array["osplatform"]!="")
    {
    $build['osplatform'] = $buildinformation_array["osplatform"];
    }
  if($buildinformation_array["osrelease"]!="")
    {
    $build['osrelease'] = $buildinformation_array["osrelease"];
    }
  if($buildinformation_array["osversion"]!="")
    {
    $build['osversion'] = $buildinformation_array["osversion"];
    }
  if($buildinformation_array["compilername"]!="")
    {
    $build['compilername'] = $buildinformation_array["compilername"];
    }
  if($buildinformation_array["compilerversion"]!="")
    {
    $build['compilerversion'] = $buildinformation_array["compilerversion"];
    }
  }
$response['build'] = $build;
$response['csvlink'] = htmlspecialchars($_SERVER["REQUEST_URI"])."&export=csv";
$project = array();
$project['showtesttime'] = $projectshowtesttime;
$response['project'] = $project;


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

$response['displaydetails'] = $displaydetails;
$response['display'] = $display;

// Filters:
//
$filterdata = get_filterdata_from_request();
unset($filterdata['xml']);
$response['filterdata'] = $filterdata;
$filter_sql = $filterdata['sql'];
$limit_sql = '';
if ($filterdata['limit']>0)
  {
  $limit_sql = ' LIMIT '.$filterdata['limit'];
  }

$limitnew = "";
$onlydelta_extra = "";
if($onlydelta)
  {
  $limitnew = " AND newstatus=1 ";
  $onlydelta_extra = " AND build2test.newstatus=1 ";
  }

$sql = "SELECT bt.status,bt.newstatus,bt.timestatus,t.id,bt.time,t.details,t.name " .
       "FROM test as t,build2test as bt " .
       "WHERE bt.buildid='$buildid' AND t.id=bt.testid " . $status . " " .
       $filter_sql . " " .$limitnew.
       "ORDER BY t.id" . $limit_sql;

$result = pdo_query($sql);

$numPassed = 0;
$numFailed = 0;
$numNotRun = 0;
$numTimeFailed = 0;


$columns = array();
$getcolumnnumber=pdo_query("SELECT testmeasurement.name, COUNT(DISTINCT test.name) as xxx FROM test
JOIN testmeasurement ON (test.id = testmeasurement.testid)
JOIN build2test ON (build2test.testid = test.id)
JOIN build ON (build.id = build2test.buildid)
JOIN measurement ON (test.projectid=measurement.projectid AND testmeasurement.name=measurement.name)
WHERE build.id='$buildid'
AND measurement.testpage=1
GROUP by testmeasurement.name
"); // We need to keep the count of columns for correct column-data assign

while($row=pdo_fetch_array($getcolumnnumber))
  {
  $columns[]=$row["name"];
  }
$response['columnnames'] = $columns;

$columncount=pdo_num_rows($getcolumnnumber);
// If at least one column is selected
$extras = "";
if($onlypassed) $extras .= "AND build2test.status='passed'";
elseif($onlyfailed) $extras .= "AND build2test.status='failed'";
elseif($onlynotrun) $extras .= "AND build2test.status='notrun'";

$getalltestlistsql="SELECT test.id
  FROM test
  JOIN build2test ON (build2test.testid = test.id)
  JOIN build ON (build.id = build2test.buildid)
  WHERE build.id='$buildid' $onlydelta_extra
  $extras
  ORDER BY test.id
";

// Allocate empty array for all possible measurements
$tmpr = array();
$getalltestlist=pdo_query($getalltestlistsql);
while($row = pdo_fetch_array($getalltestlist))
  {
    for($i=0;$i<$columncount;$i++)
      {
      $tmpr[$row['id']][$columns[$i]]="";
      }
  }

$etestquery = NULL;

if($columncount>0)
  {
  $etestquery=pdo_query("SELECT test.id, test.projectid, build2test.buildid,
  build2test.status, build2test.timestatus, test.name, testmeasurement.name,
  testmeasurement.value, build.starttime,
  build2test.time, measurement.testpage FROM test
  JOIN testmeasurement ON (test.id = testmeasurement.testid)
  JOIN build2test ON (build2test.testid = test.id)
  JOIN build ON (build.id = build2test.buildid)
  JOIN measurement ON (test.projectid=measurement.projectid AND testmeasurement.name=measurement.name)
  WHERE build.id= '$buildid'
  AND measurement.testpage=1 $onlydelta_extra
  $extras
  ORDER BY test.id, testmeasurement.name
  ");
  }


if(@$_GET['export']=="csv") // If user wants to export as CSV file
  {
  header("Cache-Control: public");
  header("Content-Description: File Transfer");
  header("Content-Disposition: attachment; filename=testExport.csv"); // Prepare some headers to download
  header("Content-Type: application/octet-stream;");
  header("Content-Transfer-Encoding: binary");
  $filecontent = "Name,Time,Details,Status,Time Status"; // Standard columns

  // Store named measurements in an array
  while($row = pdo_fetch_array($etestquery))
    {
    $etest[$row['id']][$row['name']]=$row['value'];
    }

  for($c=0;$c<count($columns);$c++)
    {
    $filecontent .= ",".$columns[$c]; // Add selected columns to the next
    }
  $filecontent .= "\n";

  while($row = pdo_fetch_array($result))
    {
    $currentStatus = $row["status"];
    $testName = $row["name"];

    $filecontent .= "$testName,{$row["time"]},{$row["details"]},";

    if($projectshowtesttime)
      {
      if($row["timestatus"] < $testtimemaxstatus)
        {
        $filecontent.="Passed,";
        }
      else
        {
        $filecontent.="Failed,";
        }
      } // end projectshowtesttime

    switch($currentStatus)
      {
      case "passed":
        $filecontent.="Passed,";
        break;
      case "failed":
        $filecontent.="Failed,";
        break;
      case "notrun":
        $filecontent.="Not Run,";
        break;
      }
    // start writing test results
    for($t=0;$t<count($columns);$t++) $filecontent .= $etest[$row['id']][$columns[$t]].",";
    $filecontent .= "\n";
    }
  echo ($filecontent); // Start file download
  die; // to suppress unwanted output
  }

// Start creating etests for each column with matching buildid, testname and the value.
$etests = array();
$i=0;
$currentcolumn=-1;
$prevtestid=0;
$checkarray = array();

// Overwrite the empty values with the correct ones if exists
while($etestquery && $row=pdo_fetch_array($etestquery))
  {
  $tmpr[$row['id']][$row['name']]=$row['value'];
  }

// Write everything we have in the array
foreach ($tmpr as $testid => $testname)
  {
  foreach ($testname as $val)
    {
    $etest =array();
    $etest['name'] = key($testname);
    $etest['testid'] = $testid;
    $etest['value'] = $val;
    $etests[] = $etest;
    }
  }
$response['etests'] = $etests;

// Gather test info
$tests = array();

// Find the time to run all the tests
$time_array = pdo_fetch_array(pdo_query("SELECT SUM(time) FROM build2test WHERE buildid='$buildid'"));
$time = $time_array[0];
$response['totaltime'] = time_difference($time, true, '', true);

while($row = pdo_fetch_array($result))
  {
  $currentStatus = $row["status"];
  $previousStatus;
  $testName = $row["name"];

  $test = array();
  $test['name'] =  $testName;
  if($row["newstatus"])
    {
    $test['new'] = "1";
    }
  $test['execTimeFull'] = $row["time"];
  $test['execTime'] = time_difference($row["time"],true,'',true);
  $test['details'] = $row["details"];
  $testdate = get_dashboard_date_from_build_starttime($build_array["starttime"],$nightlytime);
  $summaryLink = "testSummary.php?project=$projectid&name=".urlencode($testName)."&date=$testdate";
  $test['summaryLink'] = $summaryLink;
  $testid = $row["id"];
  $detailsLink = "testDetails.php?test=$testid&build=$buildid";
  $test['detailsLink'] = $detailsLink;
  $test['id'] = $testid;

  if($projectshowtesttime)
    {
    if($row["timestatus"] < $testtimemaxstatus)
      {
      $test['timestatus'] = "Passed";
      $test['timestatusclass'] = "normal";
      }
    else
      {
      $test['timestatus'] = "Failed";
      $test['timestatusclass'] = "error";
      }
    } // end projectshowtesttime

  switch($currentStatus)
    {
    case "passed":
      $test['status'] = "Passed";
      $test['statusclass'] = "normal";
      $numPassed++;
      break;
    case "failed":
      $test['status'] = "Failed";
      $test['statusclass'] = "error";
      $numFailed++;
      break;
    case "notrun":
      $test['status'] = "Not Run";
      $test['statusclass'] = "warning";
      $numNotRun++;
      break;
    }

  if($row["timestatus"] >= $testtimemaxstatus)
    {
    $numTimeFailed++;
    }

  $testid = $row['id'];

  get_labels_JSON_from_query_results(
    "SELECT text FROM label, label2test WHERE ".
    "label.id=label2test.labelid AND ".
    "label2test.testid='$testid' AND ".
    "label2test.buildid='$buildid' ".
    "ORDER BY text ASC",
    $test);


  // Search for recent test history
  if ($previous_buildids_str != "")
    {
    $history_query = "
      SELECT DISTINCT status FROM build2test
      WHERE testid=$testid AND buildid IN ($previous_buildids_str)";
    $history_results = pdo_query($history_query);
    $num_statuses = pdo_num_rows($history_results);
    if($num_statuses > 0)
      {
      $response['displayhistory'] = 1;
      if($num_statuses > 1)
        {
        $test['history'] = "Unstable";
        $test['historyclass'] = "warning";
        }
      else
        {
        $row = pdo_fetch_array($history_results);

        $test['history'] = ucfirst($row['status']);

        switch($row['status'])
          {
          case "passed":
            $test['historyclass'] = "normal";
            $test['history'] = "Stable";
            break;
          case "failed":
            $test['historyclass'] = "error";
            $test['history'] = "Broken";
            break;
          case "notrun":
            $test['historyclass'] = "warning";
            $test['history'] = "Inactive";
            break;
          }
        }
      }
    }

  // Check the status of this test on other current builds.
  list ($previousdate, $currentstarttime, $nextdate,$today) =
    get_dates($date,$nightlytime);

  $beginning_timestamp = $currentstarttime;
  $end_timestamp = $currentstarttime+3600*24;

  $beginning_UTCDate = gmdate(FMT_DATETIME,$beginning_timestamp);
  $end_UTCDate = gmdate(FMT_DATETIME,$end_timestamp);

  $summary_query = "
    SELECT DISTINCT status FROM build2test AS b2t
    LEFT JOIN build AS b ON (b2t.buildid=b.id)
    WHERE b2t.testid=$testid
    AND b.starttime>='$beginning_UTCDate'
    AND b.starttime<'$end_UTCDate'";
  $summary_results = pdo_query($summary_query);
  $num_statuses = pdo_num_rows($summary_results);
  if($num_statuses > 0)
    {
    $response['displaysummary'] = 1;
    if($num_statuses > 1)
      {
      $test['summary'] = "Unstable";
      $test['summaryclass'] = "warning";
      }
    else
      {
      $row = pdo_fetch_array($summary_results);

      $test['summary'] = ucfirst($row['status']);

      switch($row['status'])
        {
        case "passed":
          $test['summaryclass'] = "normal";
          $test['summary'] = "Stable";
          break;
        case "failed":
          $test['summaryclass'] = "error";
          $test['summary'] = "Broken";
          break;
        case "notrun":
          $test['summaryclass'] = "warning";
          $test['summary'] = "Inactive";
          break;
        }
      }
    }

  $tests[] = $test;
  }
$response['tests'] = $tests;
$response['numPassed'] = $numPassed;
$response['numFailed'] = $numFailed;
$response['numNotRun'] = $numNotRun;
$response['numTimeFailed'] = $numTimeFailed;

$end = microtime_float();
$response['generationtime'] = round($end-$start,3);
$response['columncount'] = $columncount;

echo json_encode($response);
?>
