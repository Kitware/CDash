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
    $response = array();
    $logged_in = false;
    $userid = '';

    $noforcelogin = 1;
    include 'public/login.php';
    if (isset($_SESSION['cdash']) && isset($_SESSION['cdash']['loginid'])) {
        $userid = $_SESSION['cdash']['loginid'];
        $logged_in = true;
    }
    if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid, 1)) {
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

/**
 * Pulls the buildid from the request
 *
 * @param bool $required
 * @return int
 */
function get_request_build_id($required = true)
{
    $buildid = isset($_REQUEST['buildid']) ? $_REQUEST['buildid'] : null;
    if ($required && (!$buildid || !is_numeric($buildid))) {
        json_error_response(['error' => 'Valid build ID required']);
    }
    return (int)pdo_real_escape_string($buildid);
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
