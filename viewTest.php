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

$start = microtime_float();
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$build_array = pdo_fetch_array(pdo_query("SELECT * FROM build WHERE id='$buildid'"));
$projectid = $build_array["projectid"];
if(!isset($projectid) || $projectid==0)
  {
  echo "This build doesn't exist. Maybe it has been deleted.";
  exit();
  }

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


$xml = begin_XML_for_XSLT();

$xml .= "<title>CDash : ".$projectname."</title>";

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
else if(isset($_GET["onlydelta"])) // new test that are showing up for this category
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
$xml .= "<csvlink>".htmlspecialchars($_SERVER["REQUEST_URI"])."&amp;export=csv</csvlink>";
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
$limit_sql = '';
if ($filterdata['limit']>0)
{
  $limit_sql = ' LIMIT '.$filterdata['limit'];
}
$xml .= $filterdata['xml'];

$limitnew = "";
if($onlydelta)
  {
  $limitnew = " AND newstatus=1 ";
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
  $xml .= add_XML_value("columnname",$row["name"])."\n";
  $columns[]=$row["name"];
  }

$columncount=pdo_num_rows($getcolumnnumber);
// If at least one column is selected
if($onlypassed) $extras="AND build2test.status='passed'";
elseif($onlyfailed) $extras="AND build2test.status='failed'";
elseif($onlynotrun) $extras="AND build2test.status='notrun'";
$extras.=" ORDER BY test.id, testmeasurement.name";

if($columncount>0)
  {
  $etestquery=pdo_query("SELECT test.id, test.projectid, build2test.buildid,
  build2test.status, build2test.timestatus, test.name, testmeasurement.name,
  testmeasurement.value, build.starttime,
  build2test.time, measurement.testpage FROM `test`
  JOIN testmeasurement ON (test.id = testmeasurement.testid)
  JOIN build2test ON (build2test.testid = test.id)
  JOIN build ON (build.id = build2test.buildid)
  JOIN measurement ON (test.projectid=measurement.projectid AND testmeasurement.name=measurement.name)
  WHERE build.id= '$buildid'
  AND measurement.testpage=1
  $extras
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

  for($c=0;$c<count($columns);$c++) $filecontent .= ",".$columns[$c]; // Add selected columns to the next

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
$xml .= "<etests>\n"; // Start creating etests for each column with matching buildid, testname and the value.
$i=0;
$currentcolumn=-1;
$prevtestid=0;
if($columncount>0)
  {
  while($row=pdo_fetch_array($etestquery))
    {
    if(!@in_array($row["id"],$checkarray[$row["name"]]))
      {
      for($columnkey=0;$columnkey<$columncount;$columnkey++)
        {
        if($columns[$columnkey]==$row['name'])
          {
          $columnkey+=1;
          break;
          }
        }
      $currentcolumn=($currentcolumn+1)%$columncount; // Go to next column
      if($currentcolumn!=$columnkey-1) // If data does not belong to this column
        {
        for($t=0;$t<$columncount;$t++)
          {
          if(($currentcolumn+$t)%$columncount!=$columnkey-1) // Add blank values till you find the required column
            {
            $xml .="<etest>\n";
            $xml .= add_XML_value("name","");
            $xml .= add_XML_value("testid", "");
            $xml .= add_XML_value("value", "");
            $xml .= "\n</etest>\n";
            }
          else
            {
            $currentcolumn=($currentcolumn+$t)%$columncount; // Go to next column again
            break;
            }
          }
        // Add correct values to correct column
        $xml .="<etest>\n";
        $xml .= add_XML_value("name",$row["name"]);
        $xml .= add_XML_value("testid", $row["id"]);
        $xml .= add_XML_value("value", $row["value"]);
        $xml .= "\n</etest>\n";
        $checkarray[$row["name"]][$i]=$row["id"];
        }
      else
        {
        // Add correct values to correct column
        $xml .="<etest>\n";
        $xml .= add_XML_value("name",$row["name"]);
        $xml .= add_XML_value("testid", $row["id"]);
        $xml .= add_XML_value("value", $row["value"]);
        $xml .= "\n</etest>\n";
        $checkarray[$row["name"]][$i]=$row["id"];
        }
      }
    $i++;
    }
    }
$xml .= "</etests>\n";


$xml .= "<etests>\n"; // Start creating etests for each column with matching buildid, testname and the value.
$i=0;
$currentcolumn=-1;
$checkarray = array();
if($columncount>0)
  {
  while($etestquery && $row=pdo_fetch_array($etestquery))
    {
    if(!isset($checkarray[$row["name"]]) || !in_array($row["id"],$checkarray[$row["name"]]))
      {
      for($columnkey=0;$columnkey<$columncount;$columnkey++)
        {
        if($columns[$columnkey]==$row['name'])
          {
          $columnkey+=1;
          break;
          }
        }
      $currentcolumn=($currentcolumn+1)%$columncount; // Go to next column
      if($currentcolumn!=$columnkey-1) // If data does not belong to this column
        {
        for($t=0;$t<$columncount;$t++)
          {
          if(($currentcolumn+$t)%$columncount!=$columnkey-1) // Add blank values till you find the required column
            {
            $xml .="<etest>\n";
            $xml .= add_XML_value("name","");
            $xml .= add_XML_value("testid", "");
            $xml .= add_XML_value("value", "");
            $xml .= "\n</etest>\n";
            }
          else
            {
            $currentcolumn=($currentcolumn+$t)%$columncount; // Go to next column again
            break;
            }
          }
        // Add correct values to correct column
        $xml .="<etest>\n";
        $xml .= add_XML_value("name",$row["name"]);
        $xml .= add_XML_value("testid", $row["id"]);
        $xml .= add_XML_value("value", $row["value"]);
        $xml .= "\n</etest>\n";
        $checkarray[$row["name"]][$i]=$row["id"];
        }
      else
        {
        // Add correct values to correct column
        $xml .="<etest>\n";
        $xml .= add_XML_value("name",$row["name"]);
        $xml .= add_XML_value("testid", $row["id"]);
        $xml .= add_XML_value("value", $row["value"]);
        $xml .= "\n</etest>\n";
        $checkarray[$row["name"]][$i]=$row["id"];
        }
      $prevtestid=$row["id"];
      }
    else
      {
      if ($prevtestid!=$row["id"] and $prevtestid!=0 and $currentcolumn!=0)
        {
          $xml .="<etest>\n";
          $xml .= add_XML_value("name","");
          $xml .= add_XML_value("testid", "");
          $xml .= add_XML_value("value", "");
          $xml .= "\n</etest>\n";
          $xml .="<etest>\n";
          $xml .= add_XML_value("name","");
          $xml .= add_XML_value("testid", "");
          $xml .= add_XML_value("value", "");
          $xml .= "\n</etest>\n";


        }
      // Add correct values to correct column
      $xml .="<etest>\n";
      $xml .= add_XML_value("name",$row["name"]);
      $xml .= add_XML_value("testid", $row["id"]);
      $xml .= add_XML_value("value", $row["value"]);
      $xml .= "\n</etest>\n";
      $checkarray[$row["name"]][$i]=$row["id"];
      $prevtestid=$row["id"];
      }
    $i++;
    }
  }
$xml .= "</etests>\n";

// Gather test info
$xml .= "<tests>\n";

// Find the time to run all the tests
$time_array = pdo_fetch_array(pdo_query("SELECT SUM(time) FROM build2test WHERE buildid='$buildid'"));
$time = $time_array[0];
$xml .= add_XML_value("totaltime", time_difference($time,true,'',true));

while($row = pdo_fetch_array($result))
  {
  $currentStatus = $row["status"];
  $previousStatus;
  $testName = $row["name"];

  $xml .= "<test>\n";
  $xml .= add_XML_value("name", $testName);
  if($row["newstatus"])
    {
    $xml .= add_XML_value("new","1");
    }
  $xml .= add_XML_value("execTimeFull",$row["time"]);
  $xml .= add_XML_value("execTime",  time_difference($row["time"],true,'',true));
  $xml .= add_XML_value("details", $row["details"]);
  $testdate = get_dashboard_date_from_build_starttime($build_array["starttime"],$nightlytime);
  $summaryLink = "testSummary.php?project=$projectid&name=".urlencode($testName)."&date=$testdate";
  $xml .= add_XML_value("summaryLink", $summaryLink);
  $testid = $row["id"];
  $detailsLink = "testDetails.php?test=$testid&build=$buildid";
  $xml .= add_XML_value("detailsLink", $detailsLink);
  $xml .= add_XML_value("id", $testid);

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
      $xml .= add_XML_value("status", "Passed");
      $xml .= add_XML_value("statusclass", "normal");
      $numPassed++;
      break;
    case "failed":
      $xml .= add_XML_value("status", "Failed");
      $xml .= add_XML_value("statusclass", "error");
      $numFailed++;
      break;
    case "notrun":
      $xml .= add_XML_value("status", "Not Run");
      $xml .= add_XML_value("statusclass", "warning");
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
$xml .= "<columncount>$columncount</columncount>";
$xml .= "</cdash>";
// Now doing the xslt transition
generate_XSLT($xml,"viewTest");
?>
