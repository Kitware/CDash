<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use BadMethodCallException;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        throw new BadMethodCallException('Policy method viewAny not implemented for Project');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Project $project): bool
    {
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

    /**
     * Determine whether the user can create models.
     */
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

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool
    {
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

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        throw new BadMethodCallException('Policy method delete not implemented for Project');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        throw new BadMethodCallException('Policy method restore not implemented for Project');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        throw new BadMethodCallException('Policy method forceDelete not implemented for Project');
    }
}
