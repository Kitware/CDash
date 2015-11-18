<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
$noforcelogin = 1;
include(dirname(dirname(dirname(__DIR__)))."/config/config.php");
require_once('include/pdo.php');
include_once('include/common.php');
include('web/login.php');
include('include/version.php');
include_once('models/project.php');
include_once('models/subproject.php');
include_once('models/user.php');

$start = microtime_float();

$response = begin_JSON_response();
$response['backurl'] = 'user.php';
$response['menutitle'] = 'CDash';
$response['menusubtitle'] = 'SubProjects';
$response['hidenav'] = 1;

if (!$session_OK) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}

@$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

@$userid = $_SESSION['cdash']['loginid'];
// Checks
if (!isset($userid) || !is_numeric($userid)) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}


// List the available projects that this user has admin rights to.

@$projectid = $_GET['projectid'];
$User = new User;
$User->Id = $userid;

$sql = "SELECT id,name FROM project";
if ($User->IsAdmin() == false) {
    $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid='$userid' AND role>0)";
}

$projects = pdo_query($sql);
$availableprojects = array();
while ($project_array = pdo_fetch_array($projects)) {
    $availableproject = array();
    $availableproject['id'] = $project_array['id'];
    $availableproject['name'] = $project_array['name'];
    if ($project_array['id']==$projectid) {
        $availableproject['selected'] = '1';
    }
    $availableprojects[] = $availableproject;
}
$response['availableprojects'] = $availableprojects;

if (!isset($projectid)) {
    $response['error'] = "Please select a project to continue.";
    echo json_encode($response);
    return;
}

$projectid = pdo_real_escape_numeric($projectid);
$response['projectid'] = $projectid;

$Project = new Project;
$Project->Id = $projectid;
$role = $Project->GetUserRole($userid);

if ($User->IsAdmin()===false && $role<=1) {
    $response['error'] = "You don't have the permissions to access this page.";
    echo json_encode($response);
    return;
}
$user_response = array();
$user_response['admin'] = 1;
$user_response['projectrole'] = $role;
$response['user'] = $user_response;

$response['threshold'] = $Project->GetCoverageThreshold();

$SubProject = new SubProject();
$SubProject->SetProjectId($projectid);

if ($projectid>=0) {
    $project = array();
    $project['id'] = $Project->Id;
    $project['name_encoded'] = urlencode($Project->GetName());
    $response['project'] = $project;

    if ($projectid>0) {
        $project['name'] = $Project->GetName();
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
            $group['coverage_threshold'] = $subProjectGroup->GetCoverageThreshold();
            $groups[] = $group;
            if ($subProjectGroup->GetIsDefault()) {
                $response['default_group_id'] = $group['id'];
            }
        }
        $response['groups'] = $groups;
    } // end projectid > 0
}

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);
echo json_encode(cast_data_for_JSON($response));
