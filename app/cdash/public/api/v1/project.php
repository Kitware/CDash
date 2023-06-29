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

namespace CDash\Api\v1\Project;

require_once 'include/pdo.php';
require_once 'include/api_common.php';
require_once 'include/common.php';


use CDash\Model\Project;
use CDash\Model\UserProject;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

// Read input parameters (if any).
$rest_input = file_get_contents('php://input');
if (!is_array($rest_input)) {
    $rest_input = json_decode($rest_input, true);
}
if (is_array($rest_input)) {
    $_REQUEST = array_merge($_REQUEST, $rest_input);
}

if (!Auth::check()) {
    return;
}

// Get the authenticated user.
$user = Auth::user();

// Route based on what type of request this is.
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'DELETE':
        rest_delete();
        break;
    case 'POST':
        rest_post($user);
        break;
    case 'GET':
    default:
        rest_get($user);
        break;
}

/** Handle DELETE requests */
function rest_delete()
{
    $response = [];
    $project = get_project($response);
    if (!$project) {
        echo json_encode($response);
        return;
    }
    if (!can_administrate_project($project->Id)) {
        return;
    }
    remove_project_builds($project->Id);
    $project->Delete();
    http_response_code(200);
}

/** Handle POST requests */
function rest_post($user)
{
    $response = [];

    // If we should create a new project.
    if (isset($_REQUEST['Submit'])) {
        if (!$user) {
            return;
        }

        if (!Gate::allows('create-project')) {
            // User does not have permission to create a new project.
            $response['error'] = 'You do not have permission to access this page.';
            http_response_code(403);
            return;
        }
        create_project($response, $user);
        echo json_encode($response);
        return;
    }

    $project = get_project($response);
    if (!$project) {
        echo json_encode($response);
        return;
    }
    if (!can_administrate_project($project->Id)) {
        return;
    }

    // If we should update an existing project.
    if (isset($_REQUEST['Update']) || isset($_REQUEST['AddRepository'])) {
        update_project($response, $project, $user);
        echo json_encode($response);
        return;
    }

    // If we should block a spammer's build.
    if (isset($_REQUEST['AddBlockedBuild']) && !empty($_REQUEST['AddBlockedBuild'])) {
        $response['blockedid'] =
            add_blocked_build($project, $_REQUEST['AddBlockedBuild']);
        echo json_encode($response);
        return;
    }

    // If we should remove a build from the blocked list.
    if (isset($_REQUEST['RemoveBlockedBuild']) && !empty($_REQUEST['RemoveBlockedBuild'])) {
        remove_blocked_build($project, $_REQUEST['RemoveBlockedBuild']);
        return;
    }

    // If we should set the logo.
    if (isset($_FILES['logo']) && strlen($_FILES['logo']['tmp_name']) > 0) {
        set_logo($project);
    }
}

function get_repo_url_example()
{
    require_once 'include/common.php';
    require_once 'include/repository.php';
    $url = get_param('url');
    $type = get_param('type');
    $functionname = "get_{$type}_diff_url";
    $example = $functionname($url, 'DIRECTORYNAME', 'FILENAME', 'REVISION');
    json_error_response(['example' => $example], 200);
    return true;
}

/** Handle GET requests */
function rest_get($user)
{
    // Repository URL examples?
    if (isset($_REQUEST['vcsexample'])) {
        return get_repo_url_example();
    }

    $response = [];
    $project = get_project($response);
    if (!$project) {
        echo json_encode($response);
        return;
    }
    if (!can_administrate_project($project->Id)) {
        return;
    }
    $response['project'] = $project->ConvertToJSON($user);
    echo json_encode($response);
    http_response_code(200);
}

function get_project(&$response)
{
    // Make sure we have a projectid.
    if (!isset($_REQUEST['project'])) {
        $response['error'] = 'No projectid specified';
        http_response_code(400);
        return false;
    }
    if (!is_array($_REQUEST['project'])) {
        $_REQUEST['project'] = json_decode($_REQUEST['project'], true);
    }
    if (!isset($_REQUEST['project']['Id'])) {
        $response['error'] = 'No projectid specified';
        http_response_code(400);
        return false;
    }
    $projectid = $_REQUEST['project']['Id'];
    if (!is_numeric($projectid) || $projectid < 1) {
        $response['error'] = 'No projectid specified';
        http_response_code(400);
        return false;
    }
    // Make sure the project exists.
    $Project = new Project;
    $Project->Id = $projectid;
    if (!$Project->Exists()) {
        $response['error'] = 'This project does not exist.';
        http_response_code(400);
        return false;
    }

    return $Project;
}

function create_project(&$response, $user)
{
    $Name = $_REQUEST['project']['Name'];

    if (!Project::validateProjectName($Name)) {
        $response['error'] = 'Project name contains invalid characters or patterns.';
        http_response_code(400);
        return;
    }

    // Make sure that a project with this name does not already exist.
    $Project = new Project();
    if ($Project->ExistsByName($Name)) {
        $response['error'] = "Project '$Name' already exists.";
        http_response_code(400);
        return;
    }

    // Create the project.
    $Project->Name = $Name;
    populate_project($Project);
    $Project->InitialSetup();

    // Add the current user to this project.
    if ($user->id != 1) {
        // Global admin is already added, so no need to do it again.
        $UserProject = new UserProject();
        $UserProject->UserId = $user->id;
        $UserProject->ProjectId = $Project->Id;
        $UserProject->Role = 2;
        $UserProject->EmailType = 3;// receive all emails
        $UserProject->Save();
    }

    $response['projectcreated'] = 1;
    $response['project'] = $Project->ConvertToJSON($user);
    http_response_code(200);
}

function update_project(&$response, $Project, $User)
{
    $Project->Fill();
    populate_project($Project);
    $response['projectupdated'] = 1;
    $response['project'] = $Project->ConvertToJSON($User);
    http_response_code(200);
}

function populate_project($Project)
{
    $project_settings = $_REQUEST['project'];

    if (isset($project_settings['CvsUrl'])) {
        $cvsurl = filter_var($project_settings['CvsUrl'], FILTER_SANITIZE_URL);
        $cvsurl = htmlspecialchars($cvsurl, ENT_QUOTES, 'UTF-8', false);
        $project_settings['CvsUrl'] = str_replace('&amp;', '&', $cvsurl);
    }

    foreach ($project_settings as $k => $v) {
        $Project->{$k} = $v;
    }

    // Convert UploadQuota from GB to bytes.
    if (is_numeric($Project->UploadQuota) && $Project->UploadQuota > 0) {
        $Project->UploadQuota =
            floor(min($Project->UploadQuota, config('cdash.max_upload_quota')) * 1024 * 1024 * 1024);
    }

    $Project->Save();

    if (isset($project_settings['repositories'])) {
        // Add the repositories.
        $repo_urls = array();
        $repo_branches = array();
        $repo_usernames = array();
        $repo_passwords = array();
        foreach ($project_settings['repositories'] as $repo) {
            $repo_urls[] = $repo['url'];
            $repo_branches[] = $repo['branch'];
            $repo_usernames[] = $repo['username'];
            $repo_passwords[] = $repo['password'];
        }
        if (!empty($repo_urls)) {
            $Project->AddRepositories($repo_urls, $repo_usernames,
                $repo_passwords, $repo_branches);
        }
    }
}

function add_blocked_build(Project $Project, $blocked_build)
{
    return $Project->AddBlockedBuild($blocked_build['buildname'],
        $blocked_build['sitename'], $blocked_build['ipaddress']);
}

function remove_blocked_build(Project $Project, $blocked_build): void
{
    $Project->RemoveBlockedBuild($blocked_build['id']);
}

function set_logo($Project): void
{
    $handle = fopen($_FILES['logo']['tmp_name'], 'r');
    $contents = 0;
    if ($handle) {
        $contents = fread($handle, $_FILES['logo']['size']);
        $filetype = $_FILES['logo']['type'];
        fclose($handle);
        unset($handle);
    }
    if ($contents) {
        $imageId = $Project->AddLogo($contents, $filetype);
        $response['imageid'] = $imageId;
        http_response_code(200);
        echo json_encode($response);
    }
}
