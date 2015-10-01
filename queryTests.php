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

// queryTests.php displays test results based on query parameters
//
$noforcelogin = 1;
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include("cdash/version.php");
require_once("filterdataFunctions.php");


@$date = $_GET["date"];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

@$projectname = $_GET["project"];
if ($projectname != null) {
    $projectname = htmlspecialchars(pdo_real_escape_string($projectname));
}


$start = microtime_float();

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

if ($projectname == '') {
    $project = pdo_single_row_query("SELECT * FROM project LIMIT 1");
} else {
    $project = pdo_single_row_query("SELECT * FROM project WHERE name='$projectname'");
}

checkUserPolicy(@$_SESSION['cdash']['loginid'], $project['id']);

list($previousdate, $currentstarttime, $nextdate) =
  get_dates($date, $project['nightlytime']);

$xml = begin_XML_for_XSLT();
$xml .= "<title>CDash : ".$project['name']."</title>";
$xml .= get_cdash_dashboard_xml_by_name($project['name'], $date);

// Filters:
//
$filterdata = get_filterdata_from_request();
$filter_sql = $filterdata['sql'];
$limit_sql = '';
if ($filterdata['limit']>0) {
    $limit_sql = ' LIMIT '.$filterdata['limit'];
}
$xml .= $filterdata['xml'];


$xml .= "<menu>";

if ($date == '') {
    $back = "index.php?project=".urlencode($project['name']);
} else {
    $back = "index.php?project=".urlencode($project['name'])."&date=".$date;
}

$xml .= add_XML_value("back", $back);

$limit_param = "&limit=".$filterdata['limit'];

$xml .= add_XML_value("previous",
  "queryTests.php?project=".urlencode($project['name'])."&date=".$previousdate.$limit_param);

$xml .= add_XML_value("current",
  "queryTests.php?project=".urlencode($project['name']).$limit_param);

if (has_next_date($date, $currentstarttime)) {
    $xml .= add_XML_value("next",
    "queryTests.php?project=".urlencode($project['name'])."&date=".$nextdate.$limit_param);
} else {
    $xml .= add_XML_value("nonext", "1");
}

$xml .= "</menu>";


$xml .= "<project>";
$xml .= add_XML_value("showtesttime", $project['showtesttime']);
$xml .= "</project>";


//get information about all the builds for the given date and project
$xml .= "<builds>\n";

$beginning_timestamp = $currentstarttime;
$end_timestamp = $currentstarttime + 3600*24;

$beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
$end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

// Add the date/time
$xml .= add_XML_value("projectid", $project['id']);
$xml .= add_XML_value("currentstarttime", $currentstarttime);
$xml .= add_XML_value("teststarttime", date(FMT_DATETIME, $beginning_timestamp));
$xml .= add_XML_value("testendtime", date(FMT_DATETIME, $end_timestamp));


$date_clause = '';
if (!$filterdata['hasdateclause']) {
    $date_clause = "AND b.starttime>='$beginning_UTCDate' AND b.starttime<'$end_UTCDate'";
}


$query = "SELECT
            b.id, b.name, b.starttime, b.siteid,
            build2test.testid AS testid, build2test.status, build2test.time, build2test.timestatus,
            site.name AS sitename,
            test.name AS testname, test.details
          FROM build AS b
          JOIN build2test ON (b.id = build2test.buildid)
          JOIN site ON (b.siteid = site.id)
          JOIN test ON (test.id = build2test.testid)
          WHERE b.projectid = '" . $project['id'] . "' " .
          $date_clause . " " .
          $filter_sql .
          "ORDER BY build2test.status, test.name".
          $limit_sql;

$result = pdo_query($query);


while ($row = pdo_fetch_array($result)) {
    $buildid = $row["id"];
    $testid = $row["testid"];

    $xml .= "<build>\n";

    $xml .= add_XML_value("testname", $row["testname"]);
    $xml .= add_XML_value("site", $row["sitename"]);
    $xml .= add_XML_value("buildName", $row["name"]);

    $xml .= add_XML_value("buildstarttime",
    date(FMT_DATETIMETZ, strtotime($row["starttime"]." UTC")));
    // use the default timezone, same as index.php

  $xml .= add_XML_value("time", $row["time"]);
    $xml .= add_XML_value("details", $row["details"]) . "\n";

    $siteLink = "viewSite.php?siteid=".$row["siteid"];
    $xml .= add_XML_value("siteLink", $siteLink);

    $buildSummaryLink = "buildSummary.php?buildid=$buildid";
    $xml .= add_XML_value("buildSummaryLink", $buildSummaryLink);

    $testDetailsLink = "testDetails.php?test=$testid&build=$buildid";
    $xml .= add_XML_value("testDetailsLink", $testDetailsLink);

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

    if ($project['showtesttime']) {
        if ($row["timestatus"] < $project['testtimemaxstatus']) {
            $xml .= add_XML_value("timestatus", "Passed");
            $xml .= add_XML_value("timestatusclass", "normal");
        } else {
            $xml .= add_XML_value("timestatus", "Failed");
            $xml .= add_XML_value("timestatusclass", "error");
        }
    }

    $xml .= "</build>\n";
}

$xml .= "</builds>\n";

$end = microtime_float();
$xml .= "<generationtime>".round($end-$start, 3)."</generationtime>";

$xml .= "</cdash>\n";

generate_XSLT($xml, "queryTests");
