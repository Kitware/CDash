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

namespace CDash\Api\v1\AddBuild;

require_once 'include/api_common.php';

use App\Services\AuthTokenService;
use CDash\Model\Build;
use App\Models\Site;
use CDash\ServiceContainer;
use Symfony\Component\HttpFoundation\Response;

/**********************************************************************************
 * NOTE: THIS API ENDPOINT IS DEPRECATED AND WILL BE REMOVED IN A FUTURE RELEASE. *
 **********************************************************************************/

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    return response('Not Implemented', Response::HTTP_NOT_IMPLEMENTED);
}

init_api_request();
$project = just_get_project_from_request();
$project->Fill();

$token_hash = AuthTokenService::hashToken(AuthTokenService::getBearerToken());
if ($project->AuthenticateSubmissions && !AuthTokenService::checkToken($token_hash, $project->Id)) {
    return response('Unauthorized', Response::HTTP_UNAUTHORIZED);
}

// Get the id of the specified site.
$service = ServiceContainer::getInstance();
$sitename = get_param('site');
$site = Site::firstOrCreate(['name' => $sitename], ['name' => $sitename]);

// Populate a Build object with the properties needed to generate a UUID.
$build = $service->create(Build::class);
$build->Name = get_param('name');
$build->SetStamp(get_param('stamp'));
$build->ProjectId = $project->Id;
$build->SiteId = $site->id;
$build->StartTime = gmdate(FMT_DATETIME);
$build->SubmitTime = $build->StartTime;
$subProjectName = get_param('subProjectName', false);
if ($subProjectName) {
    $build->SetSubProject($subProjectName);
}

// Call AddBuild() to create the build or get the ID of an existing build.
$build_created = $build->AddBuild();
if (!$build->Id) {
    abort(500, 'Error creating build');
}

$response = [
    'buildid' => $build->Id,
    'deprecated' => true,
];
if ($build_created) {
    return response()->json($response, 201);
} else {
    return response()->json($response, 200);
}
