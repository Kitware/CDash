<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(?User $user, Project $project): bool
    {
        if (!$project->exists()) {
            return false;
        }

        // If the project is public we return true.
        if ($project->public === Project::ACCESS_PUBLIC) {
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

        // Any user who is logged in can view protected projects.
        if ($project->public === Project::ACCESS_PROTECTED) {
            return true;
        }

        // Private projects can only be viewed by members who are explicitly added.
        if ($project->public === Project::ACCESS_PRIVATE && $project->users()->where('id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        // System-wide admins can always create projects
        if ($user->admin) {
            return true;
        }

        // Allow access if the instance is configured to allow users to create projects.
        if ((bool) config('cdash.user_create_projects')) {
            return true;
        }

        return false;
    }

    public function update(User $user, Project $project): bool
    {
        if (!$project->exists()) {
            return false;
        }

        // System-wide admins can edit any project
        if ($user->admin) {
            return true;
        }

        // Users can edit projects they administer
        if ($project->administrators()->where('id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    public function changeUserRole(User $currentUser, Project $project, User $userToChange): bool
    {
        // Users cannot change their own role.
        if ($currentUser->id === $userToChange->id) {
            return false;
        }

        // Can't change the role for users who aren't in the project...
        if (!$project->users()->where('id', $userToChange->id)->exists()) {
            return false;
        }

        return $this->update($currentUser, $project);
    }

    public function inviteUser(User $currentUser, Project $project): bool
    {
        // The project_admin_registration_form_enabled setting controls whether project admins are able to invite
        // users to their project or not.
        if (!((bool) config('cdash.project_admin_registration_form_enabled')) && !$currentUser->admin) {
            return false;
        }

        return $this->update($currentUser, $project);
    }

    public function revokeInvitation(User $currentUser, Project $project): bool
    {
        return $this->inviteUser($currentUser, $project);
    }
}
