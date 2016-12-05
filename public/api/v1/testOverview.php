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

include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/common.php';
require_once 'include/filterdataFunctions.php';
require_once 'include/version.php';
require_once 'models/project.php';

$noforcelogin = 1;
include 'public/login.php';

$start = microtime_float();
$response = array();

// Make sure a project was requested.
if (!isset($_GET['project'])) {
    $response['error'] = 'Project not specified.';
    echo json_encode($response);
    http_response_code(400);
    return;
}

// Make sure the project exists.
$projectname = $_GET['project'];
$projectid = get_project_id($projectname);
$Project = new Project();
$Project->Id = $projectid;
if (!$Project->Exists()) {
    $response['error'] = 'Project does not exist.';
    echo json_encode($response);
    http_response_code(400);
    return;
}

// Load project data.
$Project->Fill();
$has_subprojects = $Project->GetNumberOfSubProjects() > 0;

// Make sure the user has access to this project.
$logged_in = false;
if (isset($_SESSION['cdash']) && isset($_SESSION['cdash']['loginid'])) {
    $logged_in = true;
}
if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid, 1)) {
    if ($logged_in) {
        $response['error'] = 'You do not have permission to access this page.';
        echo json_encode($response);
        http_response_code(403);
    } else {
        $response['requirelogin'] = 1;
        echo json_encode($response);
        http_response_code(401);
    }
    return;
}

// Begin our JSON response.
$response = begin_JSON_response();
$response['title'] = "$projectname : Test Overview";
$response['showcalendar'] = 1;
$response['hassubprojects'] = $has_subprojects;

// Handle the optional arguments that dictate our time range.
$date = null;
$begin_date = null;
$end_date = null;
if (isset($_GET['from']) || isset($_GET['to'])) {
    if (isset($_GET['from']) && isset($_GET['to'])) {
        // If both arguments were specified, compute date range for SQL query.
        $from = $_GET['from'];
        list($unused, $beginning_timestamp, $unused, $unused) =
            get_dates($from, $Project->NightlyTime);
        $begin_date = gmdate(FMT_DATETIME, $beginning_timestamp);
        $response['from_date'] = $from;

        $date = $_GET['to'];
        list($previousdate, $end_timestamp, $nextdate, $unused) =
            get_dates($date, $Project->NightlyTime);
        $end_timestamp += (3600 * 24);
        $end_date = gmdate(FMT_DATETIME, $end_timestamp);
        $response['to_date'] = $date;
    } else {
        // If not, just use whichever one was set.
        if (isset($_GET['from'])) {
            $date = $_GET['from'];
        } else {
            $date = $_GET['to'];
        }
    }
} elseif (isset($_GET['date'])) {
    $date = $_GET['date'];
}

if (is_null($begin_date)) {
    list($previousdate, $beginning_timestamp, $nextdate, $d) =
        get_dates($date, $Project->NightlyTime);
    if (is_null($date)) {
        $date = $d;
    }
    $end_timestamp = $beginning_timestamp + 3600 * 24;
    $begin_date = gmdate(FMT_DATETIME, $beginning_timestamp);
    $end_date = gmdate(FMT_DATETIME, $end_timestamp);
}

// Check if the user specified a buildgroup.
$groupid = 0;
$group_join = '';
$group_clause = "b.type != 'Experimental'";
$group_link = '';
if (isset($_GET['group']) && is_numeric($_GET['group']) && $_GET['group'] > 0) {
    $groupid = $_GET['group'];
    $group_join = 'JOIN build2group b2g ON (b2g.buildid=b.id)';
    $group_clause = "b2g.groupid=:groupid";
    $group_link = "&group=$groupid";
}
$response['groupid'] = $groupid;

get_dashboard_JSON($projectname, $date, $response);

// Setup the menu of relevant links.
$menu = array();
$menu['previous'] = 'testOverview.php?project=' . urlencode($projectname) . "&date=$previousdate$group_link";
if ($date != '' && date(FMT_DATE, $beginning_timestamp) != date(FMT_DATE)) {
    $menu['next'] = 'testOverview.php?project=' . urlencode($projectname) . "&date=$nextdate$group_link";
} else {
    $menu['nonext'] = '1';
}
$today = date(FMT_DATE);
$menu['current'] = 'testOverview.php?project=' . urlencode($projectname) . "&date=$today$group_link";
$menu['back'] = 'index.php?project=' . urlencode($projectname) . "&date=$date";
$response['menu'] = $menu;

// List all active buildgroups for this project.
$pdo = get_link_identifier()->getPdo();
$stmt = $pdo->prepare(
    "SELECT id, name, position FROM buildgroup bg
    JOIN buildgroupposition bgp on (bgp.buildgroupid=bg.id)
    WHERE projectid=?
    AND bg.endtime='1980-01-01 00:00:00'");
$stmt->execute(array($projectid));
$groups_response = array();

// Begin with an entry for the default "Non-Experimental Builds" selection.
$default_group = array();
$default_group['id'] = 0;
$default_group['name'] = 'Non-Experimental Builds';
$default_group['position'] = 0;
$groups_response[] = $default_group;

while ($row = $stmt->fetch()) {
    $group_response = array();
    $group_response['id'] = $row['id'];
    $group_response['name'] = $row['name'];
    $group_response['position'] = $row['position'];
    $groups_response[] = $group_response;
}
$response['groups'] = $groups_response;

// Filters
$filterdata = get_filterdata_from_request();
unset($filterdata['xml']);
$response['filterdata'] = $filterdata;
$filter_sql = $filterdata['sql'];
$response['filterurl'] = get_filterurl();

$sp_select = '';
$sp_join = '';
if ($has_subprojects) {
    $sp_select = ', sp.name AS subproject';
    $sp_join = '
        JOIN subproject2build AS sp2b ON (sp2b.buildid=b.id)
        JOIN subproject AS sp ON (sp2b.subprojectid=sp.id)';
}

// Main query: find all the requested tests.
$stmt = $pdo->prepare(
    "SELECT t.name, t.details, b2t.status $sp_select FROM build b
    JOIN build2test b2t ON (b2t.buildid=b.id)
    JOIN test t ON (t.id=b2t.testid)
    $group_join
    $sp_join
    WHERE b.projectid = :projectid AND b.parentid != -1 AND $group_clause
    AND b.starttime < :end AND b.starttime >= :begin
    $filter_sql");
$stmt->bindParam(':projectid', $projectid);
$stmt->bindParam(':begin', $begin_date);
$stmt->bindParam(':end', $end_date);
if ($groupid > 0) {
    $stmt->bindParam(':groupid', $groupid);
}
$stmt->execute();

$tests_response[] = array();
$all_tests = array();
while ($row = $stmt->fetch()) {
    // Only track tests that passed or failed.
    $status = $row['status'];
    if ($status !== 'passed' && $status !== 'failed') {
        continue;
    }

    $test_name = $row['name'];
    if (!array_key_exists($test_name, $all_tests)) {
        $test = array();
        $test['name'] = $test_name;
        if ($has_subprojects) {
            $test['subproject'] = $row['subproject'];
        }
        $test['passed'] = 0;
        $test['failed'] = 0;
        $test['timeout'] = 0;
        $all_tests[$test_name] = $test;
    }

    if ($status === 'passed') {
        $all_tests[$test_name]['passed'] += 1;
    } elseif (strpos($row['details'], 'Timeout') !== false) {
        $all_tests[$test_name]['timeout'] += 1;
    } else {
        $all_tests[$test_name]['failed'] += 1;
    }
}

// Compute fail percentage for each test found.
$tests_response = array();
foreach ($all_tests as $name => $test) {
    $total_runs = $test['passed'] + $test['failed'] + $test['timeout'];
    // Avoid divide by zero.
    if ($total_runs === 0) {
        continue;
    }
    // Only include tests that failed at least once.
    if ($test['failed'] === 0 && $test['timeout'] === 0) {
        continue;
    }

    $test_response = array();
    $test_response['name'] = $name;
    if ($has_subprojects) {
        $test_response['subproject'] = $test['subproject'];
    }
    $test_response['failpercent'] =
            round(($test['failed'] / $total_runs) * 100, 2);
    $test_response['timeoutpercent'] =
            round(($test['timeout'] / $total_runs) * 100, 2);
    $test_response['link'] =
            "testSummary.php?project=$projectid&name=$name&date=$date";
    $test_response['totalruns'] = $total_runs;
    $tests_response[] = $test_response;
}

$response['tests'] = $tests_response;

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);
echo json_encode($response);
