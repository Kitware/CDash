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
require_once 'include/pdo.php';
require_once 'include/common.php';
require_once 'include/api_common.php';

use App\Models\Measurement;
use App\Services\PageTimer;
use CDash\Model\Project;

// Require administrative access to view this page.
init_api_request();

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
    Measurement::destroy($id);
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
        $id = $measurement_data['id'];
        if ($id > 0) {
            // Update an existing measurement rather than creating a new one.
            $measurement = Measurement::find($id);
        } else {
            $measurement = new Measurement();
        }
        $measurement->projectid = $projectid;
        $measurement->name = $measurement_data['name'];
        $measurement->position = $measurement_data['position'];
        if (!$measurement->save()) {
            $OK = false;
        } elseif ($id < 1) {
            // Report the ID of the newly created measurement (if any).
            $new_ID = $measurement->id;
        }
    }
    if ($OK) {
        http_response_code(200);
        if ($new_ID) {
            $response = ['id' => $new_ID];
            echo json_encode($response);
        }
    } else {
        http_response_code(500);
    }
}

/* Handle GET requests */
function rest_get($projectid)
{
    $pageTimer = new PageTimer();
    $response = begin_JSON_response();

    $project = new Project();
    $project->Id = $projectid;
    $project->Fill();

    get_dashboard_JSON($project->GetName(), null, $response);
    $response['title'] = "CDash - $project->Name Test Measurements";

    // Menu
    $menu_response = [];
    $menu_response['back'] = 'user.php';
    $response['menu'] = $menu_response;
    $response['hidenav'] =  true;

    // Get any measurements associated with this project's tests.
    $measurements_response = [];
    $measurements = Measurement::where('projectid', $projectid)
        ->orderBy('position', 'asc')
        ->get();

    foreach ($measurements as $measurement) {
        $measurement_response = [];
        $measurement_response['id'] = $measurement->id;
        $measurement_response['name'] = $measurement->name;
        $measurement_response['position'] = $measurement->position;
        $measurements_response[] = $measurement_response;
    }
    $response['measurements'] = $measurements_response;
    $pageTimer->end($response);
    echo json_encode(cast_data_for_JSON($response));
}
