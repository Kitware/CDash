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

    public static function userCanViewProject(Project $project)
    {
        if (!$project->Exists()) {
            return false;
        }

        // If the project is public we return true.
        $project->Fill();
        if ($project->Public == Project::ACCESS_PUBLIC) {
            return true;
        }

        // If not a public project, return false if the user is not logged in.
        if (!\Auth::check()) {
            return false;
        }

        $user = \Auth::user();

        // Global admins have access to all projects.
        if ($user->IsAdmin()) {
            return true;
        }

        // Logged in users can view protected projects.
        if ($project->Public == Project::ACCESS_PROTECTED) {
            return true;
        }

        // Private projects can only be viewed by users that are members.
        if ($project->Public == Project::ACCESS_PRIVATE) {
            $userproject = new UserProject();
            $userproject->UserId = $user->id;
            $userproject->ProjectId = $project->Id;
            if ($userproject->Exists()) {
                return true;
            }
            return false;
        }
        return false;
    }
}
