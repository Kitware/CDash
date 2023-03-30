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

namespace CDash\Api\v1\ManageOverview;

require_once 'include/pdo.php';
include_once 'include/common.php';

use App\Services\PageTimer;
use CDash\Database;
use CDash\Model\Project;
use Illuminate\Support\Facades\Auth;

$pageTimer = new PageTimer();
$response = begin_JSON_response();
$response['backurl'] = 'user.php';
$response['menutitle'] = 'CDash';
$response['menusubtitle'] = 'Overview';
$response['hidenav'] = 1;

// Make sure we have an authenticated user.
if (!Auth::check()) {
    $response['requirelogin'] = 1;
    return json_encode($response);
}

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
    return json_encode($response);
}

$Project = new Project();
$Project->Id = $projectid;

// Make sure the user has admin rights to this project.
get_dashboard_JSON($Project->GetName(), null, $response);
if ($response['user']['admin'] != 1) {
    $response['error'] = "You don't have the permissions to access this page";
    return json_encode($response);
}

$db = Database::getInstance();

// Check if we are saving an overview layout.
if (isset($_POST['saveLayout'])) {
    $inputRows = json_decode($_POST['saveLayout'], true);
    if (!is_null($inputRows)) {
        // Remove any old overview layout from this project.
        $db->executePrepared('DELETE FROM overview_components WHERE projectid=?', [intval($projectid)]);
        add_last_sql_error('manageOverview::saveLayout::DELETE', $projectid);

        // Construct a query to insert the new layout.
        $query = 'INSERT INTO overview_components (projectid, buildgroupid, position, type) VALUES ';
        $params = [];
        foreach ($inputRows as $inputRow) {
            $query .= '(?, ?, ?, ?),';
            $params[] = intval($projectid);
            $params[] = intval($inputRow['id']);
            $params[] = intval($inputRow['position']);
            $params[] = $inputRow['type'];
        }

        $query = rtrim($query, ',');
        $db->executePrepared($query, $params);
        add_last_sql_error('manageOverview::saveLayout::INSERT', $projectid);
    }

    // Since this is called by AJAX we don't need to generate the JSON
    // used to render this page.
    return;
}

// Otherwise generate the JSON used to render this page.
// Get the groups that are already included in the overview.
$query = $db->executePrepared('
             SELECT
                 bg.id,
                 bg.name,
                 obg.type
             FROM overview_components AS obg
             LEFT JOIN buildgroup AS bg ON (obg.buildgroupid = bg.id)
             WHERE obg.projectid = ?
             ORDER BY obg.position
         ', [intval($projectid)]);

add_last_sql_error('manageOverview::overviewgroups', $projectid);

$build_response = array();
$static_response = array();
foreach ($query as $overviewgroup_row) {
    $group_response = array();
    $group_response['id'] = intval($overviewgroup_row['id']);
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
$buildgroup_rows = $db->executePrepared('
                       SELECT
                           bg.id,
                           bg.name
                       FROM buildgroup AS bg
                       LEFT JOIN overview_components AS oc ON (bg.id = oc.buildgroupid)
                       WHERE
                           bg.projectid=?
                           AND oc.buildgroupid IS NULL
                   ', [intval($projectid)]);
add_last_sql_error('manageOverview::buildgroups', $projectid);

$availablegroups_response = array();
foreach ($buildgroup_rows as $buildgroup_row) {
    $buildgroup_response = array();
    $buildgroup_response['id'] = intval($buildgroup_row['id']);
    $buildgroup_response['name'] = $buildgroup_row['name'];
    $availablegroups_response[] = $buildgroup_response;
}
$response['availablegroups'] = $availablegroups_response;

$pageTimer->end($response);
echo json_encode(cast_data_for_JSON($response));
