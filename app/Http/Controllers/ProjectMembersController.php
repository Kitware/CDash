<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\View\View;

final class ProjectMembersController extends AbstractProjectController
{
    public function members(int $project_id): View
    {
        $this->setProjectById($project_id);

        $eloquentProject = Project::findOrFail((int) $this->project->Id);

        /** @var ?User $user */
        $user = auth()->user();

        return $this->vue('project-members-page', 'Members', [
            'project-id' => $this->project->Id,
            'user-id' => $user?->id,
            'can-invite-users' => $user?->can('inviteUser', $eloquentProject) ?? false,
            'can-remove-users' => $user?->can('removeUser', $eloquentProject) ?? false,
            'can-join-project' => $user?->can('join', $eloquentProject) ?? false,
            'can-leave-project' => $user?->can('leave', $eloquentProject) ?? false,
        ]);
    }
}
