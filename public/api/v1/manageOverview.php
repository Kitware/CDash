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
include_once 'include/common.php';
include 'public/login.php';
include 'include/version.php';

use CDash\Model\Project;
use CDash\Model\User;

$start = microtime_float();
$response = begin_JSON_response();
$response['backurl'] = 'user.php';
$response['menutitle'] = 'CDash';
$response['menusubtitle'] = 'Overview';
$response['hidenav'] = 1;

if (!$session_OK) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}

@$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

// Make sure a project was specified.
$projectid_ok = false;
@$projectid = $_GET['projectid'];
if (!isset($projectid)) {
    $rest_json = file_get_contents('php://input');
    $_POST = json_decode($rest_json, true);
    $projectid = $_POST['projectid'];
}
if (isset($projectid)) {
    $projectid = pdo_real_escape_numeric($projectid);
    if (is_numeric($projectid)) {
        $projectid_ok = true;
    }
}
if (!$projectid_ok) {
    $response['error'] = "Please specify a project";
    echo json_encode($response);
    return;
}



// Make sure we have an authenticated user.
$userid = $_SESSION['cdash']['loginid'];
if (!isset($userid) || !is_numeric($userid)) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}

$Project = new Project();
$Project->Id = $projectid;

$User = new User();
$User->Id = $userid;

// Make sure the user has admin rights to this project.
get_dashboard_JSON($Project->GetName(), null, $response);
if ($response['user']['admin'] != 1) {
    $response['error'] = "You don't have the permissions to access this page";
    echo json_encode($response);
    return;
}

// Check if we are saving an overview layout.
if (isset($_POST['saveLayout'])) {
    $inputRows = json_decode($_POST['saveLayout'], true);
    if (!is_null($inputRows)) {
        // Remove any old overview layout from this project.
        pdo_query(
                'DELETE FROM overview_components WHERE projectid=' .
                qnum(pdo_real_escape_numeric($projectid)));
        add_last_sql_error('manageOverview::saveLayout::DELETE', $projectid);

        // Construct a query to insert the new layout.
        $query = 'INSERT INTO overview_components (projectid, buildgroupid, position, type) VALUES ';
        foreach ($inputRows as $inputRow) {
            $query .= '(' .
                qnum(pdo_real_escape_numeric($projectid)) . ', ' .
                qnum(pdo_real_escape_numeric($inputRow['id'])) . ', ' .
                qnum(pdo_real_escape_numeric($inputRow['position'])) . ", '" .
                pdo_real_escape_string($inputRow['type']) . "'), ";
        }

        // Remove the trailing comma and space, then insert our new values.
        $query = rtrim($query, ', ');
        pdo_query($query);
        add_last_sql_error('manageOverview::saveLayout::INSERT', $projectid);
    }

    // Since this is called by AJAX we don't need to generate the JSON
    // used to render this page.
    return;
}

// Otherwise generate the JSON used to render this page.
// Get the groups that are already included in the overview.
$query =
    'SELECT bg.id, bg.name, obg.type FROM overview_components AS obg
    LEFT JOIN buildgroup AS bg ON (obg.buildgroupid = bg.id)
    WHERE obg.projectid = ' . qnum(pdo_real_escape_numeric($projectid)) . '
    ORDER BY obg.position';
$overviewgroup_rows = pdo_query($query);
add_last_sql_error('manageOverview::overviewgroups', $projectid);

$build_response = array();
$static_response = array();
while ($overviewgroup_row = pdo_fetch_array($overviewgroup_rows)) {
    $group_response = array();
    $group_response['id'] = $overviewgroup_row['id'];
    $group_response['name'] = $overviewgroup_row['name'];
    $type = $overviewgroup_row['type'];
    switch ($type) {
        case 'build':
            $build_response[] = $group_response;
            break;
        case 'static':
            $static_response[] = $group_response;
            break;
        default:
            add_log("Unrecognized overview group type: '$type'",
                __FILE__ . ':' . __LINE__ . ' - ' . __FUNCTION__,
                LOG_WARNING);
            break;
    }
}
$response['buildcolumns'] = $build_response;
$response['staticrows'] = $static_response;

// Get the buildgroups that aren't part of the overview yet.
$query = "SELECT bg.id, bg.name FROM buildgroup AS bg
    LEFT JOIN overview_components AS oc ON (bg.id = oc.buildgroupid)
    WHERE bg.projectid='$projectid'
    AND oc.buildgroupid IS NULL";
$buildgroup_rows = pdo_query($query);
add_last_sql_error('manageOverview::buildgroups', $projectid);
$availablegroups_response = array();
while ($buildgroup_row = pdo_fetch_array($buildgroup_rows)) {
    $buildgroup_response = array();
    $buildgroup_response['id'] = $buildgroup_row['id'];
    $buildgroup_response['name'] = $buildgroup_row['name'];
    $availablegroups_response[] = $buildgroup_response;
}
$response['availablegroups'] = $availablegroups_response;

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);
echo json_encode(cast_data_for_JSON($response));
