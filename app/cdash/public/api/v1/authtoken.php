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

declare(strict_types=1);

require_once 'include/pdo.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use App\Models\AuthToken;
use App\Services\AuthTokenService;
use Symfony\Component\HttpFoundation\Response;

/* Handle DELETE requests */
if (!function_exists('rest_delete')) {
    function rest_delete(int $userid)
    {
        $json = [];
        $hash = null;
        $response = response();
        if (isset($_REQUEST['token'])) {
            $hash = AuthTokenService::hashToken($_REQUEST['token']);
        } elseif (isset($_REQUEST['hash'])) {
            $hash = $_REQUEST['hash'];
        }
        if ($hash === null) {
            $json['error'] = 'No token or hash specified';
            return response()->json($json, Response::HTTP_BAD_REQUEST);
        }

        if (!AuthTokenService::deleteToken($hash, $userid)) {
            $json['error'] = 'Error deleting token';
            return response()->json($json, Response::HTTP_BAD_REQUEST);
        }
        return $response->json([], Response::HTTP_OK);
    }
}

/* Handle POST requests */
if (!function_exists('rest_post')) {
    function rest_post(int $userid)
    {
        $response = [];
        if (!isset($_REQUEST['description'])) {
            $response['error'] = 'No description specified';
            return response()->json($response, Response::HTTP_BAD_REQUEST);
        }

        if (!isset($_REQUEST['scope']) || !AuthTokenService::validScope($_REQUEST['scope'])) {
            $response['error'] = 'Invalid scope specified';
            return response()->json($response, Response::HTTP_BAD_REQUEST);
        }

        if ($_REQUEST['scope'] === AuthToken::SCOPE_SUBMIT_ONLY && (!isset($_REQUEST['projectid']) || !is_numeric($_REQUEST['projectid']))) {
            $response['error'] = 'Scope specified as submit only, but no project ID provided (should be -1 for all projects)';
            return response()->json($response, Response::HTTP_BAD_REQUEST);
        }

        // We use -1 as a placeholder if this is a full-scope token
        $projectid = $_REQUEST['scope'] === AuthToken::SCOPE_SUBMIT_ONLY ? $_REQUEST['projectid'] : -1;

        try {
            $gen_auth_token = AuthTokenService::generateToken($userid, intval($projectid), $_REQUEST['scope'], $_REQUEST['description']);
        } catch (InvalidArgumentException $e) {
            $response['error'] = $e->getMessage();
            return response()->json($response, Response::HTTP_BAD_REQUEST);
        }

        $response['token'] = $gen_auth_token['token'];
        $response['raw_token'] = $gen_auth_token['raw_token'];

        return response()->json($response, Response::HTTP_OK);
    }
}

// Make sure we have a valid login.
if (!Auth::check()) {
    return response('This service requires authentication');
}

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
        return rest_delete(Auth::id());
    case 'POST':
    default:
        return rest_post(Auth::id());
}
