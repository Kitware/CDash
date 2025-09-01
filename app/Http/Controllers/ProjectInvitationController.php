<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ProjectInvitationController extends AbstractController
{
    public function __invoke(Request $request, int $projectId, int $invitationId): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user === null) {
            // This case should not be possible because this route is protected by the auth middleware.
            throw new Exception('Attempt to join project without account.');
        }

        $user_invite = ProjectInvitation::find($invitationId);

        if ($user_invite === null || $user_invite->email !== $user->email) {
            abort(401, 'This invitation is not associated with your account.');
        }

        if ($projectId !== $user_invite->project_id) {
            abort(401, 'This invitation is not associated with that project.');
        }

        if ((bool) config('cdash.ldap_enabled') && $user_invite->project?->ldapfilter !== null && $user_invite->project->ldapfilter !== '') {
            abort(401, 'Membership is managed by LDAP for this project.');
        }

        if ($user->projects()->where('id', $user_invite->project_id)->exists()) {
            $user_invite->deleteOrFail();
            abort(401, 'You are already registered for this project.');
        }

        if ($user->id === $user_invite->invited_by_id) {
            // This case should never happen in theory, because users should never be able to create a self-invitation anyway.
            abort(401, 'Cannot accept self-invitation.');
        }

        $role = $user_invite->role === ProjectRole::ADMINISTRATOR ? Project::PROJECT_ADMIN : Project::PROJECT_USER;

        $user->projects()->attach($user_invite->project_id, [
            'role' => $role,
        ]);

        $user_invite->deleteOrFail();

        return response()->redirectTo("/index.php?project={$user_invite->project?->name}");
    }
}
