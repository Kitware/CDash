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

namespace CDash\Api\v1\ManageSubProject;

require_once 'include/pdo.php';
include_once 'include/common.php';

use App\Services\PageTimer;
use CDash\Model\Project;
use CDash\Model\SubProject;
use Illuminate\Support\Facades\Auth;

$pageTimer = new PageTimer();

$response = begin_JSON_response();
$response['backurl'] = 'user.php';
$response['menutitle'] = 'CDash';
$response['menusubtitle'] = 'SubProjects';
$response['hidenav'] = 1;

// Checks
if (!Auth::check()) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}

$user = Auth::user();
$userid = $user->id;

// List the available projects that this user has admin rights to.
@$projectid = $_GET['projectid'];
$sql = 'SELECT id,name FROM project';
if ($user->IsAdmin() == false) {
    $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid='$userid' AND role>0)";
}

$projects = pdo_query($sql);
$availableprojects = array();
while ($project_array = pdo_fetch_array($projects)) {
    $availableproject = array();
    $availableproject['id'] = $project_array['id'];
    $availableproject['name'] = $project_array['name'];
    if ($project_array['id'] == $projectid) {
        $availableproject['selected'] = '1';
    }
    $availableprojects[] = $availableproject;
}
$response['availableprojects'] = $availableprojects;

if (!isset($projectid) || $projectid < 1) {
    $response['error'] = 'Please select a project to continue.';
    echo json_encode($response);
    return;
}

$projectid = pdo_real_escape_numeric($projectid);
$response['projectid'] = $projectid;

$Project = new Project;
$Project->Id = $projectid;

// Make sure the user has admin rights to this project.
get_dashboard_JSON($Project->GetName(), null, $response);
if ($response['user']['admin'] != 1) {
    $response['error'] = "You don't have the permissions to access this page";
    echo json_encode($response);
    return;
}

$response['threshold'] = $Project->GetCoverageThreshold();

$SubProject = new SubProject();
$SubProject->SetProjectId($projectid);

$subprojectids = $Project->GetSubProjects();

$subprojs = array(); // subproject models
$subprojects_response = array(); // JSON for subprojects
$subproject_groups = array(); // JSON for subproject groups

// Initialize our list of subprojects so dependencies can be resolved.
// TODO: probably don't need this anymore?
foreach ($subprojectids as $subprojectid) {
    $SubProject = new SubProject();
    $SubProject->SetId($subprojectid);
    $subprojs[$subprojectid] = $SubProject;
}

foreach ($subprojectids as $subprojectid) {
    $SubProject = $subprojs[$subprojectid];
    $subproject_response = array();
    $subproject_response['id'] = $subprojectid;
    $subproject_response['name'] = $SubProject->GetName();
    $subproject_response['group'] = $SubProject->GetGroupId();
    $subprojects_response[] = $subproject_response;
}
$response['subprojects'] = $subprojects_response;

$groups = array();
foreach ($Project->GetSubProjectGroups() as $subProjectGroup) {
    $group = array();
    $group['id'] = $subProjectGroup->GetId();
    $group['name'] = $subProjectGroup->GetName();
    $group['position'] = $subProjectGroup->GetPosition();
    $group['coverage_threshold'] = $subProjectGroup->GetCoverageThreshold();
    $groups[] = $group;
    if ($subProjectGroup->GetIsDefault()) {
        $response['default_group_id'] = $group['id'];
    }
}
$response['groups'] = $groups;

$pageTimer->end($response);
echo json_encode(cast_data_for_JSON($response));
