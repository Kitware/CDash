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




use CDash\Model\Build;
use CDash\Model\Project;
use CDash\ServiceContainer;
use CDash\System;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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
function can_access_project($projectid): bool
{
    if (!$projectid) {
        return false;
    }

    $project = new Project();
    $project->Id = $projectid;
    if (Gate::allows('view-project', $project)) {
        return true;
    }

    $logged_in = Auth::check();
    if ($logged_in) {
        $response = ['error' => 'You do not have permission to access this page.'];
        json_error_response($response, 403);
    } else {
        $response = ['requirelogin' => 1];
        json_error_response($response, 401);
    }
    return false;
}

// Return true if this user has administrative access to this project.
// Respond with the correct HTTP status (401 or 403) and exit if not.
function can_administrate_project($projectid)
{
    // Check that we were supplied a valid projectid.
    $project = new Project();
    $project->Id = $projectid;
    if (!$project->Exists()) {
        json_error_response(['error' => 'Valid project ID required'], 404);
        return false;
    }

    // Make sure the user is logged in.
    if (!Auth::check()) {
        $response = ['requirelogin' => 1];
        json_error_response($response, 401);
        return false;
    }

    // Check if the user has the necessary permissions.
    if (Gate::allows('edit-project', $project)) {
        return true;
    }

    $response = ['error' => 'You do not have permission to access this page.'];
    json_error_response($response, 403);
}

/**
 * Get the named parameter from the request.
 *
 * @param bool $required
 * @return string
 */
function get_param($name, $required = true)
{
    $value = $_REQUEST[$name] ?? null;
    if ($required && !$value) {
        json_error_response(['error' => "Valid $name required"]);
        return null;
    }
    return pdo_real_escape_string($value);
}

function get_int_param($name, $required = true)
{
    $value = get_param($name, $required);
    if (is_null($value)) {
        return null;
    }

    if ($required && !is_numeric($value)) {
        json_error_response(['error' => "Valid $name required"]);
        return null;
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
    $Project = just_get_project_from_request();
    return ($Project && can_access_project($Project->Id)) ? $Project : null;
}

/**
 * Get project given request parameter, 'project'
 *
 * @return mixed|null
 */
function just_get_project_from_request()
{
    if (!isset($_REQUEST['project'])) {
        json_error_response(['error' => 'Valid project required']);
        return null;
    }
    $projectname = $_REQUEST['project'];
    $projectid = get_project_id($projectname);
    $service = ServiceContainer::getInstance();
    $Project = $service->get(Project::class);
    $Project->Id = $projectid;
    if (!$Project->Exists()) {
        json_error_response(['error' => 'Project does not exist']);
        return null;
    }
    return $Project;
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
    if (is_null($id)) {
        return null;
    }
    $build = new Build();
    $build->Id = $id;

    if ($required && !$build->Exists()) {
        $response = ['error' => 'This build does not exist. Maybe it has been deleted.'];
        json_error_response($response, 400);
        return null;
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
 * @deprecated 15/06/2023 Use abort() to exit cleanly instead.
 */
function json_error_response($response, $code = 400)
{
    $service = ServiceContainer::getInstance();
    $system = $service->get(System::class);
    http_response_code($code);
    echo json_encode($response);
    $system->system_exit();
}
