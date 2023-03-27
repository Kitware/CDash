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

namespace CDash\Api\v1\UserStatistics;

require_once 'include/pdo.php';
require_once 'include/api_common.php';

use App\Models\User;
use App\Services\PageTimer;

$pageTimer = new PageTimer();
$response = [];

// Handle required project argument.
$project_ok = true;
if (!array_key_exists('project', $_GET)) {
    $project_ok = false;
}
$projectname = $_GET['project'];
$projectid = get_project_id($projectname);
if ($projectid < 1) {
    $project_ok = false;
}
if (!$project_ok) {
    $response['error'] = 'Project name is not set.';
    echo json_encode($response);
    return 400;
}

// Check permissions.
if (!can_access_project($projectid)) {
    return;
}

// Handle optional date argument.
if (array_key_exists('date', $_GET)) {
    $date = htmlspecialchars($_GET['date']);
} else {
    $date = date(FMT_DATE);
}

$response = begin_JSON_response();
get_dashboard_JSON($projectname, $date, $response);
$response['title'] = 'CDash - Developer Statistics';
$response['showcalendar'] = 1;

// Get the requested date range.
// Default to 'week' for unexpected range values.
$range = 'week';
if (isset($_GET['range'])) {
    $range = $_GET['range'];
}
if ($range !== 'day' && $range !== 'week' && $range !== 'month' &&
        $range !== 'year') {
    $range = 'week';
}
$response['range'] = $range;

// Set up links from this page.
$menu = array();
$menu['back'] = "index.php?project=$projectname&date=$date";
$nextdate = $response['nextdate'];
$menu['next'] =
    "userStatistics.php?project=$projectname&date=$nextdate&range=$range";
$prevdate = $response['previousdate'];
$menu['previous'] =
    "userStatistics.php?project=$projectname&date=$prevdate&range=$range";
$menu['current'] =
    "userStatistics.php?project=$projectname&range=$range";
$response['menu'] = $menu;

// Set $timestamp to the end of the requested testing day so that changes
// that occurred during this day will be included.
$timestamp = $response['unixtimestamp'] + 3600 * 24;
$beginning_UTCDate = gmdate(FMT_DATETIME,
    strtotime("-1 $range", $timestamp));
$end_UTCDate = gmdate(FMT_DATETIME, $timestamp);

// Lookup stats for this time period.
$pdo = get_link_identifier()->getPdo();
$stmt = $pdo->prepare(
    'SELECT * FROM userstatistics
        WHERE checkindate<:end AND checkindate>=:beginning
        AND projectid=:projectid');
$stmt->bindParam(':end', $end_UTCDate);
$stmt->bindParam(':beginning', $beginning_UTCDate);
$stmt->bindParam(':projectid', $projectid);
pdo_execute($stmt);

$users = array();
while ($row = $stmt->fetch()) {
    if (array_key_exists($row['userid'], $users)) {
        $user = $users[$row['userid']];
    } else {
        $user = array();
        $user['nfailedwarnings'] = 0;
        $user['nfixedwarnings'] = 0;
        $user['nfailederrors'] = 0;
        $user['nfixederrors'] = 0;
        $user['nfailedtests'] = 0;
        $user['nfixedtests'] = 0;
        $user['totalbuilds'] = 0;
        $user['totalupdatedfiles'] = 0;
    }
    $user['nfailedwarnings'] += $row['nfailedwarnings'];
    $user['nfixedwarnings'] += $row['nfixedwarnings'];
    $user['nfailederrors'] += $row['nfailederrors'];
    $user['nfixederrors'] += $row['nfixederrors'];
    $user['nfailedtests'] += $row['nfailedtests'];
    $user['nfixedtests'] += $row['nfixedtests'];
    $user['totalbuilds'] += $row['totalbuilds'];
    $user['totalupdatedfiles'] += $row['totalupdatedfiles'];
    $users[$row['userid']] = $user;
}

// Generate the response used to render the main table of this page.
$users_response = array();
foreach ($users as $key => $user) {
    $user_response = array();
    $user_obj = User::where('id', $key)->first();
    $user_response['name'] = $user_obj->full_name;
    $user_response['id'] = $key;
    $user_response['failed_errors'] = $user['nfailederrors'];
    $user_response['fixed_errors'] = $user['nfixederrors'];
    $user_response['failed_warnings'] = $user['nfailedwarnings'];
    $user_response['fixed_warnings'] = $user['nfixedwarnings'];
    $user_response['failed_tests'] = $user['nfailedtests'];
    $user_response['fixed_tests'] = $user['nfixedtests'];
    $user_response['totalupdatedfiles'] = $user['totalupdatedfiles'];
    $users_response[] = $user_response;
}
$response['users'] = $users_response;
$pageTimer->end($response);
echo json_encode(cast_data_for_JSON($response));
