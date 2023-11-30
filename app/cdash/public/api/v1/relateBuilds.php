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

namespace CDash\Api\v1\RelateBuilds;

require_once 'include/api_common.php';

use App\Utils\AuthTokenService;
use CDash\Model\Build;
use CDash\Model\BuildRelationship;
use CDash\Model\Project;
use CDash\ServiceContainer;

// Get required parameters.
init_api_request();
$project = get_project_from_request();
if (is_null($project)) {
    return;
}

$buildid = get_param('buildid');
$relatedid = get_param('relatedid');

// Create objects from these parameters.
$service = ServiceContainer::getInstance();
/** @var Build $build */
$build = $service->create(Build::class);
$build->Id = $buildid;
/** @var Build $relatedbuild */
$relatedbuild = $service->create(Build::class);
$relatedbuild->Id = $relatedid;
/** @var BuildRelationship $buildRelationship */
$buildRelationship = $service->create(BuildRelationship::class);
$buildRelationship->Build = $build;
$buildRelationship->RelatedBuild = $relatedbuild;
$buildRelationship->Project = $project;

$request_method = $_SERVER['REQUEST_METHOD'];
$error_msg = '';
if ($request_method == 'GET') {
    if ($buildRelationship->Exists()) {
        $buildRelationship->Fill();
        return response()->json($buildRelationship->marshal());
    }
    abort(404, "No relationship exists between Builds $buildid and $relatedid");
}

if ($request_method == 'DELETE') {
    if (can_administrate_project($project->Id)) {
        if ($buildRelationship->Exists()) {
            if (!$buildRelationship->Delete($error_msg)) {
                if ($error_msg) {
                    abort(400, $error_msg);
                } else {
                    abort(500, 'Error deleting relationship');
                }
            }
        }
        abort(204);
    }
    return;
}

if ($request_method == 'POST') {
    // Check for valid authentication token if this project requires one.
    $project->Fill();

    $token_hash = AuthTokenService::hashToken(AuthTokenService::getBearerToken());
    if ($project->AuthenticateSubmissions && !AuthTokenService::checkToken($token_hash, $project->Id)) {
        return;
    }

    // Create or update the relationship between these two builds.
    $relationship = get_param('relationship');
    $buildRelationship->Relationship = $relationship;
    $exit_status = 200;
    if (!$buildRelationship->Exists()) {
        $exit_status = 201;
    }
    if (!$buildRelationship->Save($error_msg)) {
        if ($error_msg) {
            abort(400, $error_msg);
        } else {
            abort(500, 'Error saving relationship');
        }
    }
    return response()->json($buildRelationship->marshal(), $exit_status);
}
