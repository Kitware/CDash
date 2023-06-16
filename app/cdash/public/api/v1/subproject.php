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

namespace CDash\Api\v1\SubProject;

require_once 'include/pdo.php';
include_once 'include/common.php';

use App\Services\PageTimer;
use App\Services\ProjectPermissions;

use CDash\Database;
use CDash\Model\Project;
use CDash\Model\SubProject;
use CDash\Model\SubProjectGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

// Make sure we have a valid login.
if (!Auth::check()) {
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
$project = new Project;
$project->Id = $projectid;
if (!Gate::allows('edit-project', $project)) {
    echo_error("You don't have the permissions to access this page ($projectid)", 403);
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
    case 'PUT':
        rest_put($projectid);
        break;
    case 'GET':
    default:
        rest_get($projectid);
        break;
}

/** Handle GET requests */
function rest_get($projectid): bool
{
    $subprojectid = get_subprojectid();
    if ($subprojectid === false) {
        return true;
    }

    $projectid = intval($projectid);

    $pageTimer = new PageTimer();
    $response = begin_JSON_response();
    $response['projectid'] = $projectid;
    $response['subprojectid'] = $subprojectid;

    $SubProject = new SubProject();
    $SubProject->SetId($subprojectid);
    $response['name'] = $SubProject->GetName();
    $response['group'] = $SubProject->GetGroupId();

    $db = Database::getInstance();

    $query = $db->executePrepared("
                 SELECT id, name
                 FROM subproject
                 WHERE projectid=? AND endtime='1980-01-01 00:00:00'
             ", [$projectid]);

    if ($query === false) {
        add_last_sql_error('getSubProject Select');
        return false;
    }

    $dependencies = $SubProject->GetDependencies();
    $dependencies_response = array();
    $available_dependencies_response = array();

    foreach ($query as $row) {
        if (intval($row['id']) === $subprojectid) {
            continue;
        }
        if (is_array($dependencies) && in_array($row['id'], $dependencies)) {
            $dep = array();
            $dep['id'] = intval($row['id']);
            $dep['name'] = $row['name'];
            $dependencies_response[] = $dep;
        } else {
            $avail = array();
            $avail['id'] = intval($row['id']);
            $avail['name'] = $row['name'];
            $available_dependencies_response[] = $avail;
        }
    }

    $response['dependencies'] = $dependencies_response;
    $response['available_dependencies'] = $available_dependencies_response;

    $pageTimer->end($response);
    echo json_encode(cast_data_for_JSON($response));

    return true;
}

/** Handle DELETE requests */
function rest_delete(): void
{
    if (isset($_GET['groupid'])) {
        // Delete subproject group.
        $groupid = pdo_real_escape_numeric($_GET['groupid']);
        $Group = new SubProjectGroup();
        $Group->SetId(intval($groupid));
        $Group->Delete();
        return;
    }

    $subprojectid = get_subprojectid();
    if ($subprojectid === false) {
        return;
    }

    if (isset($_GET['dependencyid'])) {
        // Remove dependency from subproject.
        $SubProject = new SubProject();
        $SubProject->SetId($subprojectid);
        $SubProject->RemoveDependency(intval($_GET['dependencyid']));
    } else {
        // Delete subproject.
        $SubProject = new SubProject();
        $SubProject->SetId($subprojectid);
        $SubProject->Delete();
    }
}

/** Handle POST requests */
function rest_post($projectid)
{
    if (isset($_POST['newsubproject'])) {
        // Create a new subproject
        $SubProject = new SubProject();
        $SubProject->SetProjectId($projectid);

        $newSubProject =
            htmlspecialchars($_POST['newsubproject']);
        $SubProject->SetName($newSubProject);

        if (isset($_POST['group'])) {
            $SubProject->SetGroup(
                htmlspecialchars($_POST['group']));
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
        $Group->SetProjectId(intval($projectid));

        $newGroup = htmlspecialchars($_POST['newgroup'] ?? '');
        $Group->SetName($newGroup);
        if (isset($_POST['isdefault'])) {
            $Group->SetIsDefault($_POST['isdefault'] === 'true' ? 1 : 0);
        }
        $Group->SetCoverageThreshold(intval($_POST['threshold']));
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
        $db = Database::getInstance();

        // Update the order of the SubProject groups.
        $inputRows = $_POST['newLayout'];
        foreach ($inputRows as $inputRow) {
            // TODO: (williamjallen) refactor this to execute a constant number of queries
            $id = intval($inputRow['id'] ?? 0);
            $position = intval($inputRow['position'] ?? 0);
            $db->executePrepared('UPDATE subprojectgroup SET position=? WHERE id=?', [$position, $id]);
            add_last_sql_error('API::subproject::newLayout::INSERT', $projectid);
        }
    }
}

/** Handle PUT requests */
function rest_put($projectid)
{
    if (isset($_GET['threshold'])) {
        // Modify an existing subproject group.
        $groupid = intval($_GET['groupid']);
        $Group = new SubProjectGroup();
        $Group->SetProjectId(intval($projectid));
        $Group->SetId($groupid);

        $name = $_GET['name'] ?? '';
        $Group->SetName($name);

        $threshold = intval($_GET['threshold']);
        $Group->SetCoverageThreshold($threshold);

        $Group->SetIsDefault($_GET['is_default'] === 'true' ? 1 : 0);

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
        $dependencyid = intval($_GET['dependencyid']);
        $SubProject->AddDependency($dependencyid);
        return;
    }

    if (isset($_GET['groupname'])) {
        // Change which group a subproject belongs to.
        $groupName = $_GET['groupname'];
        $SubProject->SetGroup($groupName);
        $SubProject->Save();
    }
}

function get_subprojectid()
{
    if (!isset($_GET['subprojectid'])) {
        echo_error('subprojectid not specified.');
        return false;
    }
    return intval($_GET['subprojectid']);
}

function echo_error($msg, $status = 400)
{
    abort($status, $msg);
}
