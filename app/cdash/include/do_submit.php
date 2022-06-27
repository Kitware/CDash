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

use App\Services\ProjectPermissions;
use CDash\Model\AuthToken;
use CDash\Model\Project;

/**
 * Return true if the header contains a valid authentication token
 * for this project.  Otherwise return false and set the appropriate
 * response code.
 **/
function valid_token_for_submission($projectid)
{
    $authtoken = new AuthToken();
    $userid = $authtoken->getUserIdFromRequest();
    if (is_null($userid)) {
        http_response_code(401);
        return false;
    }

    // Make sure that the user associated with this token is allowed to access
    // the project in question.
    Auth::loginUsingId($userid);
    $project = new Project();
    $project->Id = $projectid;
    $project->Fill();
    if (ProjectPermissions::userCanViewProject($project)) {
        return true;
    }
    http_response_code(403);
    return false;
}
