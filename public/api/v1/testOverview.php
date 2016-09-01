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

$noforcelogin = 1;
include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
include 'public/login.php';
include_once 'include/common.php';
include 'include/version.php';

$start = microtime_float();
$response = array();

// Make sure a project was requested.
if (!isset($_GET['project'])) {
    $response['error'] = 'Project not specified.';
    echo json_encode($response);
    http_response_code(400);
    return;
}
$projectname = htmlspecialchars(pdo_real_escape_string($_GET['project']));

// Make sure the project exists & get some info about it.
$project_row = pdo_single_row_query(
    "SELECT id, nightlytime FROM project WHERE name='$projectname'");
if (!$project_row) {
    $response['error'] = 'Project does not exist.';
    echo json_encode($response);
    http_response_code(400);
    return;
}
$nightlytime = $project_row['nightlytime'];
$projectid = $project_row['id'];

// Make sure the user has access to this project.
if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid, 1)) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    http_response_code(401);
    return;
}

// Handle the optional date argument.
$date = null;
if (isset($_GET['date'])) {
    $date = htmlspecialchars(pdo_real_escape_string($_GET['date']));
}

// Handle the optional buildgroup argument.
$groupSelection = 0;
if (isset($_GET['groupSelection'])) {
    $groupSelection = pdo_real_escape_numeric($_GET['groupSelection']);
}

// Begin our JSON response.
$response = begin_JSON_response();
get_dashboard_JSON($projectname, $date, $response);
$response['title'] = "$projectname : Test Overview";
$response['groupSelection'] = strval($groupSelection);

// Setup the menu of relevant links.
list($previousdate, $currentstarttime, $nextdate, $today) = get_dates($date, $nightlytime);
$menu = array();
$menu['previous'] = 'testOverview.php?project=' . urlencode($projectname) . "&date=$previousdate";
if ($date != '' && date(FMT_DATE, $currentstarttime) != date(FMT_DATE)) {
    $menu['next'] = 'testOverview.php?project=' . urlencode($projectname) . "&date=$nextdate";
} else {
    $menu['nonext'] = '1';
}
$currentdate = get_dashboard_date_from_project($projectname, $date);
$menu['current'] = 'testOverview.php?project=' . urlencode($projectname) . "&date=$currentdate";
$menu['back'] = 'index.php?project=' . urlencode($projectname) . "&date=$currentdate";
$response['menu'] = $menu;

// Get all the active buildgroups for this project.
$groups_response = array();
$all_group = array('id' => 0, 'name' => 'All');
if ($groupSelection === 0) {
    $all_group['selected'] = 1;
}
$groups_response[] = $all_group;

$result = pdo_query(
    "SELECT id,name FROM buildgroup
    WHERE projectid='$projectid' AND endtime='1980-01-01 00:00:00'");
while ($buildgroup_row = pdo_fetch_array($result)) {
    $group_response = array();
    $group_response['id'] = $buildgroup_row['id'];
    $group_response['name'] = $buildgroup_row['name'];
    if ($groupSelection == $buildgroup_row['id']) {
        $group_response['selected'] = '1';
    }
    $groups_response[] = $group_response;
}
$response['groups'] = $groups_response;

$groupSelectionSQL = '';
if ($groupSelection > 0) {
    $groupSelectionSQL = " AND b2g.groupid='$groupSelection' ";
}

// Get each build that was submitted on this date
$rlike = 'RLIKE';
if (isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE == 'pgsql') {
    $rlike = '~';
}

$stamp = str_replace('-', '', $today);

$buildQuery = "SELECT id FROM build,build2group as b2g WHERE projectid = '$projectid'
               AND build.stamp " . $rlike . " '^$stamp-' AND b2g.buildid=build.id" . $groupSelectionSQL;

$buildResult = pdo_query($buildQuery);
$builds = array();
while ($buildRow = pdo_fetch_array($buildResult)) {
    array_push($builds, $buildRow['id']);
}

//find all the tests that were performed for this project on this date
//skip tests that passed on all builds
if (count($builds) > 0) {
    $testQuery = 'SELECT DISTINCT test.name FROM test,build2test WHERE (';
    $firstTime = true;
    foreach ($builds as $id) {
        if ($firstTime) {
            $testQuery .= "build2test.buildid='$id'";
            $firstTime = false;
        } else {
            $testQuery .= " OR build2test.buildid='$id'";
        }
    }
    $testQuery .= ") AND build2test.testid=test.id AND build2test.status NOT LIKE 'passed'";
    @$testResult = pdo_query($testQuery);
} else {
    $testResult = false;
}

$sections_response = array();

if ($testResult !== false) {
    $tests = array();
    while ($testRow = pdo_fetch_array($testResult)) {
        array_push($tests, $testRow['name']);
    }

    if (count($tests) > 0) {
        natcasesort($tests);

        // Generate the tests response.
        $letter = '';
        foreach ($tests as $testName) {
            $letter = strtolower(substr($testName, 0, 1));
            if (!array_key_exists($letter, $sections_response)) {
                $sections_response[$letter] = array();
                $sections_response[$letter]['name'] = $letter;
                $sections_response[$letter]['tests'] = array();
            }
            $test_response = array();
            $test_response['name'] = $testName;
            $summaryLink = "testSummary.php?project=$projectid&name=$testName&date=$today";
            $test_response['summaryLink'] = $summaryLink;
            $sections_response[$letter]['tests'][] = $test_response;
        }
    }
}

$response['sections'] = array_values($sections_response);

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);
echo json_encode($response);
