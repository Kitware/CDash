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
require_once 'include/api_common.php';
require_once 'include/do_submit.php';

use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\Site;
use CDash\ServiceContainer;
use Symfony\Component\HttpFoundation\Response;

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    return response('Not Implemented', Response::HTTP_NOT_IMPLEMENTED);
}
init_api_request();
$project = get_project_from_request();
// Check for valid authentication token if this project requires one.
$project->Fill();
if ($project->AuthenticateSubmissions &&
    !valid_token_for_submission($project->Id)) {
    return response()->json(['requirelogin' => 1], Response::HTTP_UNAUTHORIZED);
}

// Get the id of the specified site.
$service = ServiceContainer::getInstance();
$site = $service->create(Site::class);
$sitename = get_param('site');
$site->Name = $sitename;
$site->Insert();

// Populate a Build object with the properties needed to generate a UUID.
$build = $service->create(Build::class);
$build->Name = get_param('name');
$build->SetStamp(get_param('stamp'));
$build->ProjectId = $project->Id;
$build->SiteId = $site->Id;
$build->StartTime = gmdate(FMT_DATETIME);
$build->SubmitTime = $build->StartTime;
$subProjectName = get_param('subProjectName', false);
if ($subProjectName) {
    $build->SetSubProject($subProjectName);
}

// Call AddBuild() to create the build or get the ID of an existing build.
$build_created = $build->AddBuild();
if (!$build->Id) {
    json_error_response(['error' => 'Error creating build'], 500);
}

$response = ['buildid' => $build->Id];
if ($build_created) {
    json_error_response($response, 201);
} else {
    json_error_response($response, 200);
}
