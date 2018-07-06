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

require_once 'include/common.php';

use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\User;
use CDash\Model\UserProject;

/**
 *
 * XHR post/put/delete data not available through the traditional
 * $_POST global, this method pulls that data straight from the
 * php://input stream.
 *
 * @return void
 *
 */
function init_api_request()
{
    $method = $_SERVER['REQUEST_METHOD'];
    $isGET = $method === 'GET';

    if (!$isGET && empty($_POST)) {
        $request = file_get_contents('php://input');
        $_POST = empty($request) ? [] : json_decode($request, true);
        $_REQUEST = array_merge($_GET, $_COOKIE, $_POST);
    }
}

// Make sure this user has access to this project.
// Return true if the current user has access to view this project, or if
// the project is public (allows anonymous read access).
// Return false and respond with the correct HTTP status (401 or 403) if not.
function can_access_project($projectid)
{
    if (!$projectid) {
        return true;
    }

    $response = [];
    $logged_in = false;
    $userid = '';

    $noforcelogin = 1;
    include 'public/login.php';
    $userid = get_userid_from_session(false);
    $logged_in = is_null($userid) ? false : true;

    if (!checkUserPolicy($userid, $projectid, 1)) {
        if ($logged_in) {
            $response = ['error' => 'You do not have permission to access this page.'];
            json_error_response($response, 403);
        } else {
            $response = ['requirelogin' => 1];
            json_error_response($response, 401);
        }
        return false;
    }
    return true;
}

// Return true if this user has administrative access to this project.
// Respond with the correct HTTP status (401 or 403) and exit if not.
function can_administrate_project($projectid)
{
    // Check that we were supplied a reasonable looking projectid.
    if (!isset($projectid) || !is_numeric($projectid) || $projectid < 1) {
        json_error_response(['error' => 'Valid project ID required'], 400);
    }

    // Make sure the user is logged in.
    $userid = get_userid_from_session(false);
    if (is_null($userid)) {
        $response = ['requirelogin' => 1];
        json_error_response($response, 401);
    }

    // Check if this user is a global admin.
    $user = new User();
    $user->Id = $userid;
    if ($user->IsAdmin()) {
        return true;
    }

    // Check if this user is a project admin.
    $user2project = new UserProject();
    $user2project->UserId = $userid;
    $user2project->ProjectId = $projectid;
    $user2project->FillFromUserId();
    if ($user2project->Role == UserProject::PROJECT_ADMIN) {
        return true;
    }

    $response = ['error' => 'You do not have permission to access this page.'];
    json_error_response($response, 403);
}

/**
 * Checks for the user id in the session, if none, and required, exits programe with 401
 *
 * @return int|null
 */
function get_userid_from_session($required = true)
{
    $userid = null;
    if (isset($_SESSION['cdash']) && isset($_SESSION['cdash']['loginid'])) {
        $userid = $_SESSION['cdash']['loginid'];
    }

    if ($required && is_null($userid)) {
        $response = ['error' => 'Permission denied'];
        json_error_response($response, 403);
    }
    return $userid;
}

/**
 * Get the named parameter from the request.
 *
 * @param bool $required
 * @return string
 */
function get_param($name, $required = true)
{
    $value = isset($_REQUEST[$name]) ? $_REQUEST[$name] : null;
    if ($required && !$value) {
        json_error_response(['error' => "Valid $name required"]);
    }
    return pdo_real_escape_string($value);
}

function get_int_param($name, $required = true)
{
    $value = get_param($name, $required);
    if ($required && !is_numeric($value)) {
        json_error_response(['error' => "Valid $name required"]);
    }
    return (int)$value;
}

/**
 * Pulls the buildid from the request
 *
 * @param bool $required
 * @return int
 */
function get_request_build_id($required = true)
{
    $buildid = get_int_param('buildid', $required);
    return $buildid;
}

/**
 * Pull projectname from request and lookup its ID.
 *
 * @return Project
 */
function get_project_from_request()
{
    if (!isset($_REQUEST['project'])) {
        json_error_response(['error' => 'Valid project required']);
    }
    $projectname = $_GET['project'];
    $projectid = get_project_id($projectname);
    $Project = new Project();
    $Project->Id = $projectid;
    if (!$Project->Exists()) {
        json_error_response(['error' => 'Project does not exist']);
    }
    return can_access_project($Project->Id) ? $Project : null;
}

/**
 * Returns a build based on the id extracted from the request and returns it if the user has
 * necessary access to the project
 *
 * @param bool $required
 * @return Build|null
 */
function get_request_build($required = true)
{
    $id = get_request_build_id($required);
    $build = new Build();
    $build->Id = $id;

    if ($required && !$build->Exists()) {
        $response = ['error' => 'This build does not exist. Maybe it has been deleted.'];
        json_error_response($response, 400);
    }

    if ($id) {
        $build->FillFromId($id);
    }

    return can_access_project($build->ProjectId) ? $build : null;
}

/**
 * Issues JSON response then exits
 *
 * @param $response
 * @param int $code
 */
function json_error_response($response, $code = 400)
{
    echo json_encode($response);
    http_response_code($code);
    exit(0);
}
