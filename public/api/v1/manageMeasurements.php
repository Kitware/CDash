<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';

$noforcelogin = 1;
include 'public/login.php';

require_once 'include/common.php';
require_once 'include/api_common.php';

use CDash\Model\Measurement;
use CDash\Model\Project;

// Require administrative access to view this page.
init_api_request();
global $projectid;
$projectid = pdo_real_escape_numeric($_REQUEST['projectid']);
if (!can_administrate_project($projectid)) {
    return;
}

// Route based on what type of request this is.
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'DELETE':
        rest_delete();
        break;
    case 'POST':
        rest_post($projectid);
        break;
    case 'GET':
    default:
        rest_get($projectid);
        break;
}

/* Handle DELETE requests */
function rest_delete()
{
    $id = $_REQUEST['id'];
    if (!$id) {
        $response = ['error' => 'Invalid measurement ID provided.'];
        json_error_response($response, 400);
    }
    $measurement = new Measurement();
    $measurement->Id = $id;
    $measurement->Delete();
    http_response_code(200);
}

/* Handle POST requests */
function rest_post($projectid)
{
    if (!array_key_exists('measurements', $_REQUEST)) {
        return;
    }

    $OK = true;
    $new_ID = null;
    foreach ($_REQUEST['measurements'] as $measurement_data) {
        $measurement = new Measurement();
        $measurement->ProjectId = $projectid;
        $measurement->Name = $measurement_data['name'];
        $measurement->TestPage = $measurement_data['testpage'];
        $measurement->SummaryPage = $measurement_data['summarypage'];
        $id = $measurement_data['id'];
        if ($id > 0) {
            // Update an existing measurement rather than creating a new one.
            $measurement->Id = $id;
        }
        if (!$measurement->Save()) {
            $OK = false;
        }
        if ($id < 1) {
            // Report the ID of the newly created measurement (if any).
            $new_ID = $measurement->Id;
        }
    }

    if (!$OK) {
        http_response_code(500);
    } else {
        http_response_code(200);
        if (!is_null($new_ID)) {
            $response = ['id' => $measurement->Id];
            echo json_encode($response);
        }
    }
}

/* Handle GET requests */
function rest_get($projectid)
{
    $start = microtime_float();
    $response = begin_JSON_response();

    $project = new Project();
    $project->Id = $projectid;
    $project->Fill();

    get_dashboard_JSON($project->GetName(), null, $response);
    $response['title'] = "CDash - $project->Name Measurements";

    // Menu
    $menu_response = [];
    $menu_response['back'] = 'user.php';
    $menu_response['noprevious'] =  1;
    $menu_response['nonext'] = 1;
    $response['menu'] = $menu_response;

    // Get any measurements associated with this project's tests.
    $measurements_response = [];
    $measurement = new Measurement();
    $measurement->ProjectId = $projectid;
    foreach ($measurement->GetMeasurementsForProject() as $row) {
        $measurement_response = [];
        $measurement_response['id'] = $row['id'];
        $measurement_response['name'] = $row['name'];
        $measurement_response['testpage'] = $row['testpage'];
        $measurement_response['summarypage'] = $row['summarypage'];
        $measurements_response[] = $measurement_response;
    }
    $response['measurements'] = $measurements_response;
    $end = microtime_float();
    $response['generationtime'] = round($end - $start, 3);

    echo json_encode(cast_data_for_JSON($response));
}
