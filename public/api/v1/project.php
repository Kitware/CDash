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
include 'public/login.php';
require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'include/version.php';

use CDash\Model\Project;
use CDash\Model\User;

$userid = null;

// Read input parameters (if any).
$rest_input = file_get_contents('php://input');
if (!is_array($rest_input)) {
    $rest_input = json_decode($rest_input, true);
}
if (is_array($rest_input)) {
    $_REQUEST = array_merge($_REQUEST, $rest_input);
}

// Route based on what type of request this is.
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'DELETE':
        rest_delete();
        break;
    case 'POST':
        rest_post();
        break;
    case 'PUT':
        rest_put();
        break;
    case 'GET':
    default:
        rest_get();
        break;
}

/* Handle DELETE requests */
function rest_delete()
{
    $response = array();
    $Project = get_project($response);
    if (!$Project) {
        echo json_encode($response);
        return;
    }

    remove_project_builds($Project->Id);
    $Project->Delete();
    http_response_code(200);
}

/* Handle POST requests */
function rest_post()
{
    $response = array();

    // If we should create a new project.
    if (isset($_REQUEST['Submit'])) {
        if (!valid_user($response)) {
            return;
        }
        create_project($response);
        echo json_encode($response);
        return;
    }

    $Project = get_project($response);
    if (!$Project) {
        echo json_encode($response);
        return;
    }

    // If we should update an existing project.
    if (isset($_REQUEST['Update']) || isset($_REQUEST['AddRepository'])) {
        update_project($response, $Project);
        echo json_encode($response);
        return;
    }

    // If we should block a spammer's build.
    if (isset($_REQUEST['AddBlockedBuild']) && !empty($_REQUEST['AddBlockedBuild'])) {
        $response['blockedid'] =
            add_blocked_build($Project, $_REQUEST['AddBlockedBuild']);
        echo json_encode($response);
        return;
    }

    // If we should remove a build from the blocked list.
    if (isset($_REQUEST['RemoveBlockedBuild']) && !empty($_REQUEST['RemoveBlockedBuild'])) {
        return remove_blocked_build($Project, $_REQUEST['RemoveBlockedBuild']);
    }

    // If we should set the logo.
    if (isset($_FILES['logo']) && strlen($_FILES['logo']['tmp_name']) > 0) {
        return set_logo($Project);
    }
}

/* Handle GET requests */
function rest_get()
{
    $response = array();
    $Project = get_project($response);
    if (!$Project) {
        echo json_encode($response);
        return;
    }
    $response['project'] = $Project->ConvertToJSON();
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
    // Make sure we have an authenticated user that has access to this project.
    if (!valid_user($response, $Project)) {
        return false;
    }

    return $Project;
}

function valid_user(&$response, $Project=null)
{
    // Make sure we have a logged in user.
    global $session_OK;
    if (!$session_OK) {
        $response['requirelogin'] = 1;
        http_response_code(401);
        return false;
    }
    if (!isset($_SESSION['cdash']) || !isset($_SESSION['cdash']['loginid'])) {
        $response['requirelogin'] = 1;
        http_response_code(401);
        return false;
    }
    global $userid;
    $userid = $_SESSION['cdash']['loginid'];
    if (!isset($userid) || !is_numeric($userid)) {
        $response['requirelogin'] = 1;
        http_response_code(401);
        return false;
    }

    // Make sure this user has the necessary permissions.
    $User = new User;
    $User->Id = $userid;

    if (is_null($Project)
            && !(isset($_SESSION['cdash']['user_can_create_project']) &&
                $_SESSION['cdash']['user_can_create_project'] == 1)
            && !$User->IsAdmin()) {
        // User does not have permission to create a new project.
        $response['error'] = 'You do not have permission to access this page.';
        http_response_code(403);
        return false;
    } elseif (!is_null($Project)
            && (!$User->IsAdmin() && $Project->GetUserRole($userid) <= 1)) {
        // User does not have permission to edit this project.
        $response['error'] = 'You do not have permission to access this page.';
        http_response_code(403);
        return false;
    }

    return true;
}

/** Strip the HTTP */
function stripHTTP($url)
{
    $pos = strpos($url, 'http://');
    if ($pos !== false) {
        return substr($url, 7);
    } else {
        $pos = strpos($url, 'https://');
        if ($pos !== false) {
            return substr($url, 8);
        }
    }
    return $url;
}

function create_project(&$response)
{
    $Name = $_REQUEST['project']['Name'];
    // Remove any potentially problematic characters.
    $Name = preg_replace("/[^a-zA-Z0-9\s+-._]/", '', $Name);

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
    global $userid;
    if ($userid != 1) {
        // Global admin is already added, so no need to do it again.
        $UserProject = new UserProject();
        $UserProject->UserId = $userid;
        $UserProject->ProjectId = $Project->Id;
        $UserProject->Role = 2;
        $UserProject->EmailType = 3;// receive all emails
        $UserProject->Save();
    }

    $response['projectcreated'] = 1;
    $response['project'] = $Project->ConvertToJSON();
    http_response_code(200);
}

function update_project(&$response, $Project)
{
    $Project->Fill();
    populate_project($Project);
    $response['projectupdated'] = 1;
    $response['project'] = $Project->ConvertToJSON();
    http_response_code(200);
}

function populate_project($Project)
{
    $project_settings = $_REQUEST['project'];
    foreach ($project_settings as $k => $v) {
        $Project->{$k} = $v;
    }

    // Strip "http[s]://" from the beginning of URLs.
    $url_vars = array('HomeUrl', 'CvsUrl', 'DocumentationUrl', 'TestingDataUrl');
    foreach ($url_vars as $var) {
        $Project->{$var} = stripHTTP($Project->{$var});
    }

    // Convert UploadQuota from GB to bytes.
    global $CDASH_MAX_UPLOAD_QUOTA;
    if (is_numeric($Project->UploadQuota) && $Project->UploadQuota > 0) {
        $Project->UploadQuota =
            floor(min($Project->UploadQuota, $CDASH_MAX_UPLOAD_QUOTA) * 1024 * 1024 * 1024);
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

function add_blocked_build($Project, $blocked_build)
{
    return $Project->AddBlockedBuild($blocked_build['buildname'],
            $blocked_build['sitename'], $blocked_build['ipaddress']);
}

function remove_blocked_build($Project, $blocked_build)
{
    $Project->RemoveBlockedBuild($blocked_build['id']);
}

function set_logo($Project)
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
