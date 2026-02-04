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

        if ($this->isLdapControlledMembership($project)) {
            return false;
        }

        return $this->update($currentUser, $project);
    }

    public function revokeInvitation(User $currentUser, Project $project): bool
    {
        return $this->update($currentUser, $project);
    }

    /**
     * Whether the current user can remove other users or not.  Use leave() to determine whether
     * the current user can remove themselves from the project.
     */
    public function removeUser(User $currentUser, Project $project): bool
    {
        if ($this->isLdapControlledMembership($project)) {
            return false;
        }

        return $this->update($currentUser, $project);
    }

    public function join(User $currentUser, Project $project): bool
    {
        if (!$this->view($currentUser, $project)) {
            return false;
        }

        if ($this->isLdapControlledMembership($project)) {
            return false;
        }

        return !$project->users()->where('id', $currentUser->id)->exists();
    }

    public function leave(User $currentUser, Project $project): bool
    {
        return !$this->isLdapControlledMembership($project) && $project->users()->where('id', $currentUser->id)->exists();
    }

    public function createPinnedTestMeasurement(User $currentUser, Project $project): bool
    {
        return $this->update($currentUser, $project);
    }

    public function deletePinnedTestMeasurement(User $currentUser, Project $project): bool
    {
        return $this->update($currentUser, $project);
    }

    private function isLdapControlledMembership(Project $project): bool
    {
        // If a LDAP filter has been specified and LDAP is enabled, CDash controls the entire members list.
        return (bool) config('cdash.ldap_enabled') && $project->ldapfilter !== null && $project->ldapfilter !== '';
    }
}
