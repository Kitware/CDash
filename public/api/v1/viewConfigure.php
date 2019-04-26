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
$noforcelogin = 1;
require_once 'public/login.php';
require_once 'include/common.php';
require_once 'include/api_common.php';
require_once 'include/version.php';

use CDash\Model\BuildConfigure;
use CDash\Model\Project;
use CDash\Model\Site;

$start = microtime_float();

$build = get_request_build();
$project = new Project();
$project->Id = $build->ProjectId;
$project->Fill();

$date = get_dashboard_date_from_build_starttime($build->StartTime, $project->NightlyTime);
$response = begin_JSON_response();
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
    $menu_response['previous'] = "viewConfigure.php?buildid=$previous_buildid";
} else {
    $menu_response['previous'] = false;
}

$menu_response['current'] = "viewConfigure.php?buildid=$current_buildid";

if ($next_buildid > 0) {
    $menu_response['next'] = "viewConfigure.php?buildid=$next_buildid";
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
$site = new Site();
$site->Id = $build->SiteId;
$build_response = [];
$build_response['site'] = $site->GetName();
$build_response['siteid'] = $site->Id;
$build_response['buildname'] = $build->Name;
$build_response['buildid'] = $build->Id;
$build_response['hassubprojects'] = $has_subprojects;
$response['build'] = $build_response;

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);
echo json_encode(cast_data_for_JSON($response));
