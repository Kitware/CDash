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

$noforcelogin = 1;
include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
include_once 'include/common.php';
include 'public/login.php';
include 'include/version.php';

use CDash\Model\Project;
use CDash\Model\SubProject;
use CDash\Model\SubProjectGroup;
use CDash\Model\User;

// Make sure we have a valid login.
if (!Auth::check()) {
    return;
}
$userid = Auth::id();
if ($userid || !is_numeric($userid)) {
    echo_error('Not a valid userid!');
    return;
}

// Check required parameter.
@$projectid = $_GET['projectid'];
if (!isset($projectid)) {
    $rest_json = file_get_contents('php://input');
    $_POST = json_decode($rest_json, true);
    @$projectid = $_POST['projectid'];
}
if (!isset($projectid)) {
    echo_error('projectid not specified.');
    return;
}
$projectid = pdo_real_escape_numeric($projectid);

// Make sure the user has access to this page.
$Project = new Project;

$User = new User;
$User->Id = $userid;
$Project->Id = $projectid;

$role = $Project->GetUserRole($userid);

if ($User->IsAdmin() === false && $role <= 1) {
    echo_error("You ($userid) don't have the permissions to access this page ($projectid)");
    return;
}

// Route based on what type of request this is.
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'DELETE':
        rest_delete($projectid);
        break;
    case 'POST':
        rest_post($projectid);
        break;
    case 'PUT':
        rest_put($projectid);
        break;
    case 'GET':
    default:
        rest_get($projectid);
        break;
}

/* Handle GET requests */
function rest_get($projectid)
{
    $subprojectid = get_subprojectid();
    if ($subprojectid === false) {
        return;
    }

    $start = microtime_float();
    $response = begin_JSON_response();
    $response['projectid'] = $projectid;
    $response['subprojectid'] = $subprojectid;

    $SubProject = new SubProject();
    $SubProject->SetId($subprojectid);
    $response['name'] = $SubProject->GetName();
    $response['group'] = $SubProject->GetGroupId();

    $query = pdo_query('
    SELECT id, name FROM subproject WHERE projectid=' . qnum($projectid) . "
    AND endtime='1980-01-01 00:00:00'");

    if (!$query) {
        add_last_sql_error('getSubProject Select');
        return false;
    }

    $dependencies = $SubProject->GetDependencies();
    $dependencies_response = array();
    $available_dependencies_response = array();

    while ($row = pdo_fetch_array($query)) {
        if ($row['id'] == $subprojectid) {
            continue;
        }
        if (is_array($dependencies) && in_array($row['id'], $dependencies)) {
            $dep = array();
            $dep['id'] = $row['id'];
            $dep['name'] = $row['name'];
            $dependencies_response[] = $dep;
        } else {
            $avail = array();
            $avail['id'] = $row['id'];
            $avail['name'] = $row['name'];
            $available_dependencies_response[] = $avail;
        }
    }

    $response['dependencies'] = $dependencies_response;
    $response['available_dependencies'] = $available_dependencies_response;

    $end = microtime_float();
    $response['generationtime'] = round($end - $start, 3);
    echo json_encode(cast_data_for_JSON($response));
}

/* Handle DELETE requests */
function rest_delete($projectid)
{
    if (isset($_GET['groupid'])) {
        // Delete subproject group.
        $groupid = pdo_real_escape_numeric($_GET['groupid']);
        $Group = new SubProjectGroup();
        $Group->SetId($groupid);
        $Group->Delete();
        return;
    }

    $subprojectid = get_subprojectid();
    if ($subprojectid === false) {
        return;
    }

    if (isset($_GET['dependencyid'])) {
        // Remove dependency from subproject.
        $dependencyid = pdo_real_escape_numeric($_GET['dependencyid']);
        $SubProject = new SubProject();
        $SubProject->SetId($subprojectid);
        $SubProject->RemoveDependency($dependencyid);
    } else {
        // Delete subproject.
        $SubProject = new SubProject();
        $SubProject->SetId($subprojectid);
        $SubProject->Delete();
    }
}

/* Handle POST requests */
function rest_post($projectid)
{
    if (isset($_POST['newsubproject'])) {
        // Create a new subproject
        $SubProject = new SubProject();
        $SubProject->SetProjectId($projectid);

        $newSubProject =
            htmlspecialchars(pdo_real_escape_string($_POST['newsubproject']));
        $SubProject->SetName($newSubProject);

        if (isset($_POST['group'])) {
            $SubProject->SetGroup(
                htmlspecialchars(pdo_real_escape_string($_POST['group'])));
        }

        $SubProject->Save();

        // Respond with a JSON representation of this new subproject
        $response = array();
        $response['id'] = $SubProject->GetId();
        $response['name'] = $SubProject->GetName();
        $response['group'] = $SubProject->GetGroupId();
        echo json_encode(cast_data_for_JSON($response));
        return;
    }

    if (isset($_POST['newgroup'])) {
        // Create a new group
        $Group = new SubProjectGroup();
        $Group->SetProjectId($projectid);

        $newGroup =
            htmlspecialchars(pdo_real_escape_string($_POST['newgroup']));
        $Group->SetName($newGroup);
        if (isset($_POST['isdefault'])) {
            $Group->SetIsDefault($_POST['isdefault']);
        }
        $Group->SetCoverageThreshold(pdo_real_escape_numeric($_POST['threshold']));
        $Group->Save();

        // Respond with a JSON representation of this new group
        $response = array();
        $response['id'] = $Group->GetId();
        $response['name'] = $Group->GetName();
        $response['is_default'] = $Group->GetIsDefault();
        $response['coverage_threshold'] = $Group->GetCoverageThreshold();
        echo json_encode(cast_data_for_JSON($response));
    }

    if (isset($_POST['newLayout'])) {
        // Update the order of the SubProject groups.
        $inputRows = $_POST['newLayout'];
        foreach ($inputRows as $inputRow) {
            $id = qnum(pdo_real_escape_numeric($inputRow['id']));
            $position = qnum(pdo_real_escape_numeric($inputRow['position']));
            $query = "UPDATE subprojectgroup SET position=$position WHERE id=$id";
            pdo_query($query);
            add_last_sql_error('API::subproject::newLayout::INSERT', $projectid);
        }
        return;
    }
}

/* Handle PUT requests */
function rest_put($projectid)
{
    if (isset($_GET['threshold'])) {
        // Modify an existing subproject group.
        $groupid = pdo_real_escape_numeric($_GET['groupid']);
        $Group = new SubProjectGroup();
        $Group->SetProjectId($projectid);
        $Group->SetId($groupid);

        $name = pdo_real_escape_string($_GET['name']);
        $Group->SetName($name);

        $threshold = pdo_real_escape_numeric($_GET['threshold']);
        $Group->SetCoverageThreshold($threshold);

        $Group->SetIsDefault($_GET['is_default']);

        $Group->Save();
        return;
    }

    $subprojectid = get_subprojectid();
    if ($subprojectid === false) {
        return;
    }
    $SubProject = new SubProject();
    $SubProject->SetId($subprojectid);

    if (isset($_GET['dependencyid'])) {
        // Add dependency to existing subproject.
        $dependencyid = pdo_real_escape_numeric($_GET['dependencyid']);
        $SubProject->AddDependency($dependencyid);
        return;
    }

    if (isset($_GET['groupname'])) {
        // Change which group a subproject belongs to.
        $groupName = pdo_real_escape_string($_GET['groupname']);
        $SubProject->SetGroup($groupName);
        $SubProject->Save();
        return;
    }
}

function get_subprojectid()
{
    if (!isset($_GET['subprojectid'])) {
        echo_error('subprojectid not specified.');
        return false;
    }
    $subprojectid = pdo_real_escape_numeric($_GET['subprojectid']);
    return $subprojectid;
}

function echo_error($msg)
{
    $response = array();
    $response['error'] = $msg;
    echo json_encode($response);
}
