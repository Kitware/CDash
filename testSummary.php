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

/*
* testSummary.php displays a list of all builds that performed a given test
* on a specific day.  It also displays information (success, execution time)
* about each copy of the test that was run.
*/
$noforcelogin = 1;
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include_once("cdash/repository.php");
include("cdash/version.php");

$date = htmlspecialchars(pdo_real_escape_string($_GET["date"]));
if (!isset($date) || strlen($date)==0) {
    die('Error: no date supplied in query string');
}
$projectid = pdo_real_escape_numeric($_GET["project"]);
if (!isset($projectid)) {
    die('Error: no project supplied in query string');
}
// Checks
if (!isset($projectid) || !is_numeric($projectid)) {
    echo "Not a valid projectid!";
    return;
}

$testName = htmlspecialchars(pdo_real_escape_string($_GET["name"]));
if (!isset($testName)) {
    die('Error: no test name supplied in query string');
}

$start = microtime_float();

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);
$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if (pdo_num_rows($project)>0) {
    $project_array = pdo_fetch_array($project);
    $projectname = $project_array["name"];
    $nightlytime = $project_array["nightlytime"];
    $projectshowtesttime = $project_array["showtesttime"];
}

checkUserPolicy(@$_SESSION['cdash']['loginid'], $project_array["id"]);

$xml = begin_XML_for_XSLT();
$xml .= "<title>CDash : ".$projectname."</title>";

$xml .= get_cdash_dashboard_xml_by_name($projectname, $date);
$xml .= add_XML_value("testName", $testName);

$xml .= "<menu>";
list($previousdate, $currentstarttime, $nextdate, $today) = get_dates($date, $nightlytime);
$xml .= add_XML_value("back", "index.php?project=".urlencode($projectname)."&date=".$date);
$xml .= add_XML_value("previous", "testSummary.php?project=$projectid&name=$testName&date=$previousdate");
$xml .= add_XML_value("current", "testSummary.php?project=$projectid&name=$testName&date=".date(FMT_DATE));
if ($date!="" && date(FMT_DATE, $currentstarttime)!=date(FMT_DATE)) {
    $xml .= add_XML_value("next", "testSummary.php?project=$projectid&name=$testName&date=$nextdate");
} else {
    $xml .= add_XML_value("nonext", "1");
}
$xml .= "</menu>";
$testName = pdo_real_escape_string($testName);
list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $project_array["nightlytime"]);
$beginning_timestamp = $currentstarttime;
$end_timestamp = $currentstarttime+3600*24;

$beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
$end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

$getcolumnnumber=pdo_query("SELECT testmeasurement.name, COUNT(DISTINCT test.name) as xxx FROM test
JOIN testmeasurement ON (test.id = testmeasurement.testid)
JOIN build2test ON (build2test.testid = test.id)
JOIN build ON (build.id = build2test.buildid)
JOIN measurement ON (test.projectid=measurement.projectid AND testmeasurement.name=measurement.name)
WHERE test.name='$testName'
AND build.starttime>='$beginning_UTCDate'
AND build.starttime<'$end_UTCDate'
AND test.projectid=$projectid
AND measurement.summarypage= 1
GROUP by testmeasurement.name
"); // We need to keep the count of columns for correct column-data assign

$columns = array();
while ($row=pdo_fetch_array($getcolumnnumber)) {
    $xml .= add_XML_value("columnname", $row["name"])."\n";
    $columns[]=$row["name"];
}

$columncount=pdo_num_rows($getcolumnnumber);
// If at least one column is selected
if ($columncount>0) {
    $etestquery=pdo_query("SELECT test.id, test.projectid, build2test.buildid, build2test.status, build2test.timestatus, test.name,
        testmeasurement.name, testmeasurement.value, build.starttime, build2test.time, measurement.testpage FROM test
  JOIN testmeasurement ON (test.id = testmeasurement.testid)
  JOIN build2test ON (build2test.testid = test.id)
  JOIN build ON (build.id = build2test.buildid)
  JOIN measurement ON (test.projectid=measurement.projectid AND testmeasurement.name=measurement.name)
  WHERE test.name='$testName'
  AND build.starttime>='$beginning_UTCDate'
  AND build.starttime<'$end_UTCDate'
  AND test.projectid=$projectid
  AND measurement.summarypage= 1
  ORDER BY build2test.buildid, testmeasurement.name
  ");

    $xml .= "<etests>\n"; // Start creating etests for each column with matching buildid, testname and the value.
  $i=0;
    $currentcolumn=-1;
    $prevtestid=0;
    $checkarray = array();
    while ($etestquery && $row=pdo_fetch_array($etestquery)) {
        if (!isset($checkarray[$row["name"]]) || !in_array($row["id"], $checkarray[$row["name"]])) {
            for ($columnkey=0;$columnkey<$columncount;$columnkey++) {
                if ($columns[$columnkey]==$row['name']) {
                    $columnkey+=1;
                    break;
                }
            }
            $currentcolumn=($currentcolumn+1)%$columncount; // Go to next column
      if ($currentcolumn==0) {
          $prevtestid=$row["id"];
      }
            if ($currentcolumn!=$columnkey-1) {
                // If data does not belong to this column

        for ($t=0;$t<$columncount;$t++) {
            if (($currentcolumn+$t)%$columncount!=$columnkey-1) {
                // Add blank values till you find the required column

            $xml .="<etest>\n";
                $xml .= add_XML_value("name", "");
                $xml .= add_XML_value("testid", $row["id"]);
                $xml .= add_XML_value("buildid", $row["buildid"]);
                $xml .= add_XML_value("value", "");
                $xml .= "\n</etest>\n";
                $prevtestid=$row["id"];
            } else {
                $currentcolumn=($currentcolumn+$t)%$columncount; // Go to next column again
            break;
            }
        }
        // Add correct values to correct column
        if ($prevtestid==$row["id"] and $currentcolumn!=0) {
            $xml .="<etest>\n";
            $xml .= add_XML_value("name", $row["name"]);
            $xml .= add_XML_value("testid", $row["id"]);
            $xml .= add_XML_value("buildid", $row["buildid"]);
            $xml .= add_XML_value("value", $row["value"]);
            $xml .= "\n</etest>\n";
            $checkarray[$row["name"]][$i]=$row["id"];
            $prevtestid=$row["id"];
        } else {
            if ($prevtestid!=$row["id"] and $prevtestid!=0 and $currentcolumn!=0) {
                for ($t=0;$t<$columncount;$t++) {
                    $xml .="<etest>\n";
                    $xml .= add_XML_value("name", "");
                    $xml .= add_XML_value("testid", "");
                    $xml .= add_XML_value("buildid", "");
                    $xml .= add_XML_value("rowcheck", "-");
                    $xml .= "\n</etest>\n";
                }
            }

            $xml .="<etest>\n";
            $xml .= add_XML_value("name", $row["name"]);
            $xml .= add_XML_value("testid", $row["id"]);
            $xml .= add_XML_value("buildid", $row["buildid"]);
            $xml .= add_XML_value("value", $row["value"]);
            $xml .= "\n</etest>\n";
            $checkarray[$row["name"]][$i]=$row["id"];
            $prevtestid=$row["id"];
        }
            } else {
                if ($prevtestid!=$row["id"] and $prevtestid!=0 and $currentcolumn!=0) {
                    for ($t=0;$t<$columncount;$t++) {
                        $xml .="<etest>\n";
                        $xml .= add_XML_value("name", "");
                        $xml .= add_XML_value("testid", "");
                        $xml .= add_XML_value("buildid", "");
                        $xml .= add_XML_value("value", "");
                        $xml .= "\n</etest>\n";
                    }
                }
        // Add correct values to correct column
        $xml .="<etest>\n";
                $xml .= add_XML_value("name", $row["name"]);
                $xml .= add_XML_value("testid", $row["id"]);
                $xml .= add_XML_value("buildid", $row["buildid"]);
                $xml .= add_XML_value("value", $row["value"]);
                $xml .= "\n</etest>\n";
                $checkarray[$row["name"]][$i]=$row["id"];
                $prevtestid=$row["id"];
            }
        }
        $i++;
    }
    $xml .= "</etests>\n";
}
//Get information about all the builds for the given date and project
$xml .= "<builds>\n";

// Add the date/time
$xml .= add_XML_value("projectid", $projectid);
$xml .= add_XML_value("currentstarttime", $currentstarttime);
$xml .= add_XML_value("teststarttime", date(FMT_DATETIME, $beginning_timestamp));
$xml .= add_XML_value("testendtime", date(FMT_DATETIME, $end_timestamp));

$columncount=pdo_num_rows($getcolumnnumber);
// If at least one column is selected
if ($columncount>0) {
    $etestquery=pdo_query("SELECT test.id, test.projectid, build2test.buildid, build2test.status, build2test.timestatus, test.name, testmeasurement.name, testmeasurement.value, build.starttime, build2test.time, measurement.testpage FROM test
    JOIN testmeasurement ON (test.id = testmeasurement.testid)
    JOIN build2test ON (build2test.testid = test.id)
    JOIN build ON (build.id = build2test.buildid)
    JOIN measurements ON (test.projectid=measurement.projectid AND testmeasurement.name=measurement.name)
    WHERE test.name='$testName'
    AND build.starttime>='$beginning_UTCDate'
    AND build.starttime<'$end_UTCDate'
    AND test.projectid=$projectid
    AND measurement.summarypage= 1
    ORDER BY build2test.buildid, testmeasurement.name
    ");
}

$query = "SELECT build.id,build.name,build.stamp,build2test.status,build2test.buildid,build2test.time,build2test.testid AS testid,site.name AS sitename
          FROM build
          JOIN build2test ON (build.id = build2test.buildid)
          JOIN site ON (build.siteid = site.id)
          WHERE build.projectid = '$projectid'
          AND build.starttime>='$beginning_UTCDate'
          AND build.starttime<'$end_UTCDate'
          AND build2test.testid IN (SELECT id FROM test WHERE name='$testName')
          ORDER BY build2test.buildid";

$result = pdo_query($query);

if (isset($_GET['export']) && $_GET['export']=="csv") {
    // If user wants to export as CSV file

  header("Cache-Control: public");
    header("Content-Description: File Transfer");
    header("Content-Disposition: attachment; filename=testExport.csv"); // Prepare some headers to download
  header("Content-Type: application/octet-stream;");
    header("Content-Transfer-Encoding: binary");
    $filecontent = "Site,Build Name,Build Stamp,Status,Time(s)"; // Standard columns

  // Store named measurements in an array
  while (isset($etestquery) && $row = pdo_fetch_array($etestquery)) {
      $etest[$row['buildid']][$row['name']]=$row['value'];
  }

    for ($c=0;$c<count($columns);$c++) {
        $filecontent .= ",".$columns[$c]; // Add selected columns to the next
    }

    $filecontent .= "\n";

    while ($row = pdo_fetch_array($result)) {
        $currentStatus = $row["status"];

        $filecontent .= "{$row["sitename"]},{$row["name"]},{$row["stamp"]},{$row["time"]},";
 
        if ($projectshowtesttime) {
            if ($row["timestatus"] < $testtimemaxstatus) {
                $filecontent.="Passed,";
            } else {
                $filecontent.="Failed,";
            }
        } // end projectshowtesttime

  switch ($currentStatus) {
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
    for ($t=0;$t<count($columns);$t++) {
        $filecontent .= $etest[$row['id']][$columns[$t]].",";
    }
        $filecontent .= "\n";
    }
    echo($filecontent); // Start file download
  die; // to suppress unwanted output
}

//now that we have the data we need, generate some XML
while ($row = pdo_fetch_array($result)) {
    $buildid = $row["id"];
    $xml .= "<build>\n";

  // Find the repository revision
  $xml .= "<update>";
  // Return the status
  $status_array = pdo_fetch_array(pdo_query("SELECT status,revision,priorrevision,path
                                              FROM buildupdate,build2update AS b2u
                                              WHERE b2u.updateid=buildupdate.id
                                              AND b2u.buildid='$buildid'"));
    if (strlen($status_array["status"]) > 0 && $status_array["status"]!="0") {
        $xml .= add_XML_value("status", $status_array["status"]);
    } else {
        $xml .= add_XML_value("status", ""); // empty status
    }
    $xml .= add_XML_value("revision", $status_array["revision"]);
    $xml .= add_XML_value("priorrevision", $status_array["priorrevision"]);
    $xml .= add_XML_value("path", $status_array["path"]);
    $xml .= add_XML_value("revisionurl",
          get_revision_url($projectid, $status_array["revision"], $status_array["priorrevision"]));
    $xml .= add_XML_value("revisiondiff",
          get_revision_url($projectid, $status_array["priorrevision"], '')); // no prior prior revision...
  $xml .= "</update>";

    $xml .= add_XML_value("site", $row["sitename"]);
    $xml .= add_XML_value("buildName", $row["name"]);
    $xml .= add_XML_value("buildStamp", $row["stamp"]);
    $xml .= add_XML_value("time", $row["time"]);

//$xml .= add_XML_value("details", $row["details"]) . "\n";

  $buildLink = "viewTest.php?buildid=$buildid";
    $xml .= add_XML_value("buildid", $buildid);
    $xml .= add_XML_value("buildLink", $buildLink);
    $testid = $row["testid"];
    $testLink = "testDetails.php?test=$testid&build=$buildid";
    $xml .= add_XML_value("testLink", $testLink);
    switch ($row["status"]) {
    case "passed":
      $xml .= add_XML_value("status", "Passed");
      $xml .= add_XML_value("statusclass", "normal");
      break;
    case "failed":
      $xml .= add_XML_value("status", "Failed");
      $xml .= add_XML_value("statusclass", "error");
      break;
    case "notrun":
      $xml .= add_XML_value("status", "Not Run");
   $xml .= add_XML_value("statusclass", "warning");
      break;
    }
    $xml .= "</build>\n";
}
$xml .= "</builds>\n";
$xml .= "<csvlink>".htmlspecialchars($_SERVER["REQUEST_URI"])."&amp;export=csv</csvlink>";
$end = microtime_float();
$xml .= "<generationtime>".round($end-$start, 3)."</generationtime>";
$count=count($columns);
$xml .= "<columncount>$count</columncount>";
$xml .= "</cdash>\n";
// Now doing the xslt transition
generate_XSLT($xml, "testSummary");
