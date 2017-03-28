<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

// queryTests.php displays test results based on query parameters
//
include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/api_common.php';
include 'include/version.php';
require_once 'include/filterdataFunctions.php';
include_once 'models/build.php';

@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

// If parentid is set we need to lookup the date for this build
// because it is not specified as a query string parameter.
if (isset($_GET['parentid'])) {
    $parentid = pdo_real_escape_numeric($_GET['parentid']);
    $parent_build = new Build();
    $parent_build->Id = $parentid;
    $date = $parent_build->GetDate();
}

@$projectname = $_GET['project'];
if ($projectname != null) {
    $projectname = htmlspecialchars(pdo_real_escape_string($projectname));
}

$response = begin_JSON_response();
$response['title'] = "CDash : $projectname";
$response['showcalendar'] = 1;

$start = microtime_float();

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

if ($projectname == '') {
    $project_array = pdo_single_row_query('SELECT * FROM project LIMIT 1');
} else {
    $project_array = pdo_single_row_query("SELECT * FROM project WHERE name='$projectname'");
}

if (!can_access_project($project_array['id'])) {
    return;
}

list($previousdate, $currentstarttime, $nextdate) =
    get_dates($date, $project_array['nightlytime']);

$projectname = $project_array['name'];

get_dashboard_JSON_by_name($projectname, $date, $response);

// Filters:
//
$filterdata = get_filterdata_from_request();
unset($filterdata['xml']);
$response['filterdata'] = $filterdata;
$filter_sql = $filterdata['sql'];
$limit_sql = '';
if ($filterdata['limit'] > 0) {
    $limit_sql = ' LIMIT ' . $filterdata['limit'];
}
$response['filterurl'] = get_filterurl();

// Menu
$menu = array();
$limit_param = '&limit=' . $filterdata['limit'];
$base_url = 'queryTests.php?project=' . urlencode($project_array['name']);
if (isset($_GET['parentid'])) {
    // When a parentid is specified, we should link to the next build,
    // not the next day.
    $previous_buildid = $parent_build->GetPreviousBuildId();
    $current_buildid = $parent_build->GetCurrentBuildId();
    $next_buildid = $parent_build->GetNextBuildId();

    $menu['back'] = 'index.php?project=' . urlencode($project_array['name']) . '&parentid=' . $_GET['parentid'];

    if ($previous_buildid > 0) {
        $menu['previous'] = "$base_url&parentid=$previous_buildid" . $limit_param;
    } else {
        $menu['noprevious'] = '1';
    }

    $menu['current'] = "$base_url&parentid=$current_buildid" . $limit_param;

    if ($next_buildid > 0) {
        $menu['next'] = "$base_url&parentid=$next_buildid" . $limit_param;
    } else {
        $menu['nonext'] = '1';
    }
} else {
    if ($date == '') {
        $back = 'index.php?project=' . urlencode($project_array['name']);
    } else {
        $back = 'index.php?project=' . urlencode($project_array['name']) . '&date=' . $date;
    }
    $menu['back'] = $back;

    $menu['previous'] = $base_url . '&date=' . $previousdate . $limit_param;

    $menu['current'] = $base_url . $limit_param;

    if (has_next_date($date, $currentstarttime)) {
        $menu['next'] = $base_url . '&date=' . $nextdate . $limit_param;
    } else {
        $menu['nonext'] = '1';
    }
}

$response['menu'] = $menu;

// Project
$project = array();
$project['showtesttime'] = $project_array['showtesttime'];
$response['project'] = $project;

//get information about all the builds for the given date and project
$builds = array();

$beginning_timestamp = $currentstarttime;
$end_timestamp = $currentstarttime + 3600 * 24;

$beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
$end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

// Add the date/time
$builds['projectid'] = $project_array['id'];
$builds['currentstarttime'] = $currentstarttime;
$builds['teststarttime'] = date(FMT_DATETIME, $beginning_timestamp);
$builds['testendtime'] = date(FMT_DATETIME, $end_timestamp);

$date_clause = '';
if (!$filterdata['hasdateclause']) {
    $date_clause = "AND b.starttime>='$beginning_UTCDate' AND b.starttime<'$end_UTCDate'";
}

$parent_clause = '';
if (isset($_GET['parentid'])) {
    // If we have a parentid, then we should only show children of that build.
    // Date becomes irrelevant in this case.
    $parent_clause = 'AND (b.parentid = ' . qnum($_GET['parentid']) . ') ';
    $date_clause = '';
}

$query = "SELECT
            b.id, b.name, b.starttime, b.siteid,b.parentid,
            build2test.testid AS testid, build2test.status, build2test.time, build2test.timestatus,
            site.name AS sitename,
            test.name AS testname, test.details
          FROM build AS b
          JOIN build2test ON (b.id = build2test.buildid)
          JOIN site ON (b.siteid = site.id)
          JOIN test ON (test.id = build2test.testid)
          WHERE b.projectid = '" . $project_array['id'] . "' " .
    $parent_clause . $date_clause . ' ' .
    $filter_sql .
    'ORDER BY build2test.status, test.name' .
    $limit_sql;

$result = pdo_query($query);

// Builds
$builds = array();
while ($row = pdo_fetch_array($result)) {
    $buildid = $row['id'];
    $testid = $row['testid'];

    $build = array();

    $build['testname'] = $row['testname'];
    $build['site'] = $row['sitename'];
    $build['buildName'] = $row['name'];

    $build['buildstarttime'] =
        date(FMT_DATETIMETZ, strtotime($row['starttime'] . ' UTC'));
    // use the default timezone, same as index.php

    $build['time'] = $row['time'];
    $build['details'] = $row['details'] . "\n";

    $siteLink = 'viewSite.php?siteid=' . $row['siteid'];
    $build['siteLink'] = $siteLink;

    $buildSummaryLink = "buildSummary.php?buildid=$buildid";
    $build['buildSummaryLink'] = $buildSummaryLink;

    $testDetailsLink = "testDetails.php?test=$testid&build=$buildid";
    $build['testDetailsLink'] = $testDetailsLink;

    switch ($row['status']) {
        case 'passed':
            $build['status'] = 'Passed';
            $build['statusclass'] = 'normal';
            break;

        case 'failed':
            $build['status'] = 'Failed';
            $build['statusclass'] = 'error';
            break;

        case 'notrun':
            $build['status'] = 'Not Run';
            $build['statusclass'] = 'warning';
            break;
    }

    if ($project_array['showtesttime']) {
        if ($row['timestatus'] < $project_array['testtimemaxstatus']) {
            $build['timestatus'] = 'Passed';
            $build['timestatusclass'] = 'normal';
        } else {
            $build['timestatus'] = 'Failed';
            $build['timestatusclass'] = 'error';
        }
    }

    $builds[] = $build;
}
$response['builds'] = $builds;

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);

echo json_encode(cast_data_for_JSON($response));
