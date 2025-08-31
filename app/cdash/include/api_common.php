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

use CDash\Model\Project;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * XHR post/put/delete data not available through the traditional
 * $_POST global, this method pulls that data straight from the
 * php://input stream.
 */
function init_api_request(): void
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
        Gate::authorize('view-project', $project);
        return false;
    } else {
        throw new AuthenticationException();
    }
}

// Return true if this user has administrative access to this project.
// Respond with the correct HTTP status (401 or 403) and exit if not.
function can_administrate_project($projectid)
{
    // Check that we were supplied a valid projectid.
    $project = new Project();
    $project->Id = $projectid;
    if (!$project->Exists()) {
        abort(404, 'Valid project ID required');
    }

    // Make sure the user is logged in.
    if (!Auth::check()) {
        throw new AuthenticationException();
    }

    // Check if the user has the necessary permissions.
    if (Gate::allows('edit-project', $project)) {
        return true;
    }

    abort(403, 'You do not have permission to access this page.');
}

/**
 * Get the named parameter from the request.
 *
 * @param bool $required
 */
function get_param($name, $required = true): string
{
    $value = $_REQUEST[$name] ?? null;
    if ($required && !$value) {
        abort(400, "Valid $name required");
    }
    return pdo_real_escape_string($value);
}
