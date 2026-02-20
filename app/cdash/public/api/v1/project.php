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

use App\Rules\ProjectAuthenticateSubmissionsRule;
use App\Rules\ProjectNameRule;
use App\Rules\ProjectVisibilityRule;
use App\Services\ProjectService;
use App\Utils\RepositoryUtils;
use CDash\Model\Project;
use App\Models\Project as EloquentProject;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

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
        return rest_get();
}

/** Handle DELETE requests */
function rest_delete(): void
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
    \App\Models\Project::findOrFail((int) $project->Id)->delete();
    http_response_code(200);
}

/** Handle POST requests */
function rest_post($user): void
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
        $response['blockedid'] = EloquentProject::findOrFail((int) $project->Id)
            ->blockedbuilds()
            ->create([
                'buildname' => $_REQUEST['AddBlockedBuild']['buildname'],
                'sitename' => $_REQUEST['AddBlockedBuild']['sitename'],
                'ipaddress' => $_REQUEST['AddBlockedBuild']['ipaddress'],
            ])->id;
        echo json_encode($response);
        return;
    }

    // If we should remove a build from the blocked list.
    if (isset($_REQUEST['RemoveBlockedBuild']) && !empty($_REQUEST['RemoveBlockedBuild'])) {
        EloquentProject::findOrFail((int) $project->Id)
            ->blockedbuilds()
            ->findOrFail((int) $_REQUEST['RemoveBlockedBuild']['id'])
            ->delete();
        return;
    }

    // If we should set the logo.
    if (isset($_FILES['logo']) && strlen($_FILES['logo']['tmp_name']) > 0) {
        set_logo($project);
    }
}

function get_repo_url_example(): \Illuminate\Http\JsonResponse
{
    $url = get_param('url');
    $type = get_param('type');
    $functionname = "get_{$type}_diff_url";
    $example = RepositoryUtils::$functionname($url, 'DIRECTORYNAME', 'FILENAME', 'REVISION');
    return response()->json(['example' => $example]);
}

/** Handle GET requests */
function rest_get()
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
    $response['project'] = $project->ConvertToJSON();
    echo json_encode($response);
    http_response_code(200);
}

function get_project(&$response): false|Project
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
    $Project = new Project();
    $Project->Id = $projectid;
    if (!$Project->Exists()) {
        $response['error'] = 'This project does not exist.';
        http_response_code(400);
        return false;
    }

    return $Project;
}

function create_project(&$response, $user): void
{
    $Name = $_REQUEST['project']['Name'];

    // Make sure that a project with this name does not already exist.
    if (\App\Models\Project::where('name', $Name)->exists()) {
        $response['error'] = "Project '$Name' already exists.";
        http_response_code(400);
        return;
    }

    // Create the project.
    $Project = new Project();
    $Project->Name = $Name;
    populate_project($Project);

    $eloquent_project = EloquentProject::findOrFail((int) $Project->Id);
    ProjectService::initializeBuildGroups($eloquent_project);

    // Add the current user to this project.
    $eloquent_project->users()
        ->attach($user->id, [
            'emailtype' => 3, // receive all emails
            'emailcategory' => 126,
            'emailsuccess' => false,
            'emailmissingsites' => false,
            'role' => EloquentProject::PROJECT_ADMIN,
        ]);

    $response['projectcreated'] = 1;
    $response['project'] = $Project->ConvertToJSON();
    http_response_code(200);
}

function update_project(&$response, $Project, $User): void
{
    $Project->Fill();
    populate_project($Project);
    $response['projectupdated'] = 1;
    $response['project'] = $Project->ConvertToJSON($User);
    http_response_code(200);
}

function populate_project($Project): void
{
    $project_settings = $_REQUEST['project'];

    if (isset($project_settings['CvsUrl'])) {
        $cvsurl = filter_var($project_settings['CvsUrl'], FILTER_SANITIZE_URL);
        $cvsurl = htmlspecialchars($cvsurl, ENT_QUOTES, 'UTF-8', false);
        $project_settings['CvsUrl'] = str_replace('&amp;', '&', $cvsurl);
    }

    $validator = Validator::make([
        'name' => $project_settings['Name'],
        'visibility' => $project_settings['Public'],
        'authenticatesubmissions' => (bool) ($project_settings['AuthenticateSubmissions'] ?? false),
    ], [
        'name' => new ProjectNameRule(),
        'visibility' => new ProjectVisibilityRule(),
        'authenticatesubmissions' => new ProjectAuthenticateSubmissionsRule(),
    ]);
    if ($validator->fails()) {
        abort(403, $validator->messages());
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
        $repo_urls = [];
        $repo_branches = [];
        $repo_usernames = [];
        $repo_passwords = [];
        foreach ($project_settings['repositories'] as $repo) {
            $repo_urls[] = $repo['url'];
            $repo_branches[] = $repo['branch'];
            $repo_usernames[] = $repo['username'];
            $repo_passwords[] = $repo['password'];
        }
        if (!empty($repo_urls)) {
            addRepositoriesToProject((int) $Project->Id, $repo_urls, $repo_usernames, $repo_passwords, $repo_branches);
        }
    }
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

function addRepositoriesToProject(int $projectid, array $repositories, array $usernames, array $passwords, array $branches): void
{
    $project = \App\Models\Project::findOrFail($projectid);

    // Delete and re-add all repositories
    $project->repositories()->delete();
    foreach ($repositories as $index => $url) {
        $project->repositories()->create([
            'url' => $url,
            'username' => $usernames[$index],
            'password' => $passwords[$index],
            'branch' => $branches[$index],
        ]);
    }
}
