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

namespace App\Utils;

use CDash\Model\Project;
use CDash\Model\UserProject;
use App\Models\User;

/**
 * This class handles checks for whether a user can create or edit
 * a project.
 **/
class ProjectPermissions
{
    public static function canEditProject(Project $project, User $user): bool
    {
        // Check if this user is a global admin.
        if ($user->admin) {
            return true;
        }

        // Check if this user is a project admin.
        $user2project = new UserProject();
        $user2project->UserId = $user->id;
        $user2project->ProjectId = $project->Id;
        $user2project->FillFromUserId();
        if ($user2project->Role === UserProject::PROJECT_ADMIN) {
            return true;
        }

        return false;
    }

    public static function canViewProject(Project $project, ?User $user): bool
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
        if ($user === null) {
            return false;
        }

        // Global admins have access to all projects.
        if ($user->admin) {
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
