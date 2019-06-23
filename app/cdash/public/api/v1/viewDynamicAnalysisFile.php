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

require_once 'include/pdo.php';
require_once 'include/common.php';
require_once 'include/api_common.php';

use CDash\Model\Build;
use CDash\Model\DynamicAnalysis;
use CDash\Model\Project;
use CDash\Model\Site;

$start = microtime_float();
$response = [];

// Make sure a valid id was specified.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    json_error_response('Not a valid id!');
}
$id = $_GET['id'];
$DA = new DynamicAnalysis();
$DA->Id = $id;
if (!$DA->Fill()) {
    json_error_response('Not a valid id!');
}

// Get the build associated with this analysis.
$build = new Build();
$build->Id = $DA->BuildId;
if (!$build->Exists()) {
    json_error_response('This build does not exist. Maybe it has been deleted.');
}
$build->FillFromId($build->Id);

// Make sure the user has access to this project.
if (!can_access_project($build->ProjectId)) {
    return;
}

$project = new Project();
$project->Id = $build->ProjectId;
$project->Fill();

$date = $project->GetTestingDay($build->StartTime);
$response = begin_JSON_response();
get_dashboard_JSON($project->Name, $date, $response);
$response['title'] = "$project->Name : Dynamic Analysis";

// Build
$site = new Site();
$site->Id = $build->SiteId;
$site_name = $site->GetName();

$build_response = [];
$build_response['site'] = $site_name;
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

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);
echo json_encode(cast_data_for_JSON($response));
