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

namespace App\Services;

use CDash\Model\Project;
use CDash\Model\UserProject;
use App\Models\User;

/**
 * This class handles checks for whether a user can create or edit
 * a project.
 **/
class ProjectPermissions
{
    public static function userCanEditProject(User $user, Project $project)
    {
        // Check if this user is a global admin.
        if ($user->IsAdmin()) {
            return true;
        }

        // Check if this user is a project admin.
        $user2project = new UserProject();
        $user2project->UserId = $user->id;
        $user2project->ProjectId = $project->Id;
        $user2project->FillFromUserId();
        if ($user2project->Role == UserProject::PROJECT_ADMIN) {
            return true;
        }

        return false;
    }

    public static function userCanCreateProject(User $user)
    {
        $config = \CDash\Config::getInstance();
        return $user->IsAdmin() || $config->get('CDASH_USER_CREATE_PROJECTS');
    }
}
