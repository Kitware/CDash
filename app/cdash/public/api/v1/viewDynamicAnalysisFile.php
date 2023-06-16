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

namespace CDash\Api\v1\ViewDynamicAnalysisFile;

require_once 'include/pdo.php';
require_once 'include/common.php';
require_once 'include/api_common.php';

use App\Services\PageTimer;
use App\Services\TestingDay;

use CDash\Model\Build;
use CDash\Model\DynamicAnalysis;
use CDash\Model\Project;

$pageTimer = new PageTimer();
$response = [];

// Make sure a valid id was specified.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    abort(400, 'Not a valid id!');
}
$id = $_GET['id'];
$DA = new DynamicAnalysis();
$DA->Id = $id;
if (!$DA->Fill()) {
    abort(400, 'Not a valid id!');
}

// Get the build associated with this analysis.
$build = new Build();
$build->Id = $DA->BuildId;
if (!$build->Exists()) {
    abort(404, 'This build does not exist. Maybe it has been deleted.');
}
$build->FillFromId($build->Id);

// Make sure the user has access to this project.
if (!can_access_project($build->ProjectId)) {
    return;
}

$project = new Project();
$project->Id = $build->ProjectId;
$project->Fill();
if (!can_access_project($project->Id)) {
    $response['error'] = 'You do not have permission to view this project.';
    echo json_encode($response);
    return;
}

$date = TestingDay::get($project, $build->StartTime);
$response = begin_JSON_response();
get_dashboard_JSON($project->Name, $date, $response);
$response['title'] = "$project->Name : Dynamic Analysis";

// Build
$build_response = [];
$build_response['site'] = $build->GetSite()->name;
$build_response['buildname'] = $build->Name;
$build_response['buildid'] = $build->Id;
$build_response['buildtime'] = $build->StartTime;
$response['build'] = $build_response;

// Menu
$menu_response = [];
$menu_response['back'] = "viewDynamicAnalysis.php?buildid=$build->Id";
$previous_id = $DA->GetPreviousId($build);
if ($previous_id > 0) {
    $menu_response['previous'] = "viewDynamicAnalysisFile.php?id=$previous_id";
} else {
    $menu_response['previous'] = false;
}
$current_id = $DA->GetLastId($build);
$menu_response['current'] = "viewDynamicAnalysisFile.php?id=$current_id";
$next_id = $DA->GetNextId($build);
if ($next_id > 0) {
    $menu_response['next'] = "viewDynamicAnalysisFile.php?id=$next_id";
} else {
    $menu_response['next'] = false;
}
$response['menu'] = $menu_response;

// dynamic analysis
$DA_response = [];
$DA_response['status'] = ucfirst($DA->Status);
$DA_response['filename'] = $DA->Name;
// Only display the first 1MB of the log (in case it's huge)
$DA_response['log'] = substr($DA->Log, 0, 1024 * 1024);
$href = "testSummary.php?project=$project->Id&name=$DA->Name&date=$date";
$DA_response['href'] = $href;
$response['dynamicanalysis'] = $DA_response;

$pageTimer->end($response);
echo json_encode(cast_data_for_JSON($response));
