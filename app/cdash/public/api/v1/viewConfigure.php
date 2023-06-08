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

namespace CDash\Api\v1\ViewConfigure;

require_once 'include/pdo.php';
require_once 'include/common.php';
require_once 'include/api_common.php';

use App\Services\PageTimer;
use App\Services\TestingDay;

use CDash\Model\BuildConfigure;
use CDash\Model\Project;

$pageTimer = new PageTimer();

$build = get_request_build();
if (is_null($build)) {
    return;
}

$project = new Project();
$project->Id = $build->ProjectId;
$project->Fill();

$response = begin_JSON_response();
if (!can_access_project($project->Id)) {
    $response['error'] = 'You do not have permission to view this project.';
    echo json_encode($response);
    return;
}

$date = TestingDay::get($project, $build->StartTime);
get_dashboard_JSON($project->Name, $date, $response);
$response['title'] = "$project->Name : Configure";

// Menu
$menu_response = [];
if ($build->GetParentId() > 0) {
    $menu_response['back'] = 'index.php?project=' . urlencode($project->Name) . "&parentid={$build->GetParentId()}";
} else {
    $menu_response['back'] = 'index.php?project=' . urlencode($project->Name) . '&date=' . $date;
}

$previous_buildid = $build->GetPreviousBuildId();
$next_buildid = $build->GetNextBuildId();
$current_buildid = $build->GetCurrentBuildId();

if ($previous_buildid > 0) {
    $menu_response['previous'] = "/build/$previous_buildid/configure";
} else {
    $menu_response['previous'] = false;
}

$menu_response['current'] = "/build/$current_buildid/configure";

if ($next_buildid > 0) {
    $menu_response['next'] = "/build/$next_buildid/configure";
} else {
    $menu_response['next'] = false;
}
$response['menu'] = $menu_response;

// Configure
$configures_response = [];
$configures = $build->GetConfigures();
$has_subprojects = 0;
while ($configure = $configures->fetch()) {
    if (isset($configure['subprojectid'])) {
        $has_subprojects = 1;
    }
    $configures_response[] = buildconfigure::marshal($configure);
}
$response['configures'] = $configures_response;

// Build
$site = $build->GetSite();
$build_response = [];
$build_response['site'] = $site->name;
$build_response['siteid'] = $site->id;
$build_response['buildname'] = $build->Name;
$build_response['buildid'] = $build->Id;
$build_response['hassubprojects'] = $has_subprojects;
$response['build'] = $build_response;

$pageTimer->end($response);
echo json_encode(cast_data_for_JSON($response));
