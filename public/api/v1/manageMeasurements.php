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
require_once 'models/measurement.php';
require_once 'models/project.php';
require_once 'models/user.php';

$start = microtime_float();

// Require administrative access to view this page.
init_api_request();
$projectid = pdo_real_escape_numeric($_REQUEST['projectid']);
if (!can_administrate_project($projectid)) {
    return;
}

$project = new Project();
$project->Id = $projectid;
$project->Fill();

// Route based on what type of request this is.
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'DELETE':
        rest_delete();
        break;
    case 'POST':
        rest_post();
        break;
    case 'GET':
    default:
        rest_get();
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
function rest_post()
{
}

/* Handle GET requests */
function rest_get()
{
}

if (array_key_exists('submit', $_POST)) {
    $submit = $_POST['submit'];
    $nameN = htmlspecialchars(pdo_real_escape_string($_POST['nameN']));
    $showTN = htmlspecialchars(pdo_real_escape_string($_POST['showTN']));
    $showSN = htmlspecialchars(pdo_real_escape_string($_POST['showSN']));

    $id = $_POST['id'];
    $name = $_POST['name'];

    // Start operation if it is submitted
    if ($submit == 'Save') {
        if ($nameN) {
            // Only write a new entry if new field is filled.
            $measurement = new Measurement();
            $measurement->ProjectId = $projectid;
            $measurement->Name = $nameN;
            $measurement->TestPage = $showTN;
            $measurement->SummaryPage = $showSN;
            $measurement->Insert();
        }
        $i = 0;

        if (count($_POST['name'])) {
            foreach ($name as $newName) { // everytime update all test attributes
                $showT = $_POST['showT'];
                $showS = $_POST['showS'];
                if ($showT[$id[$i]] == '') {
                    $showT[$id[$i]] = 0;
                }
                if ($showS[$id[$i]] == '') {
                    $showS[$id[$i]] = 0;
                }

                $measurement = new Measurement();
                $measurement->ProjectId = $projectid;
                $measurement->Name = $newName;
                $measurement->TestPage = $showT[$id[$i]];
                $measurement->SummaryPage = $showS[$id[$i]];
                $measurement->Update();

                $i++;
            }
        }
    }
}

$response = begin_JSON_response();
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
    $measurement_response['showT'] = $row['testpage'];
    $measurement_response['showS'] = $row['summarypage'];
    $measurements_response[] = $measurement_response;
}
$response['measurements'] = $measurements_response;
$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);

echo json_encode(cast_data_for_JSON($response));
