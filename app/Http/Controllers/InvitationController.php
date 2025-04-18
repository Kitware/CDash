<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use App\Models\UserInvitation;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InvitationController extends AbstractController
{
    public function __invoke(Request $request, int $invitationId): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        if ($user === null) {
            // This case should not be possible because this route is protected by the auth middleware.
            throw new Exception('Attempt to join project without account.');
        }

        $user_invite = UserInvitation::find($invitationId);

        if ($user_invite === null || $user_invite->email !== $user->email) {
            abort(401, 'This invitation is not associated with your account.');
        }

        if ($user->projects()->where('id', $user_invite->project_id)->exists()) {
            $user_invite->deleteOrFail();
            abort(401, 'You are already registered for this project.');
        }

        if ($user->id === $user_invite->invited_by_id) {
            // This case should never happen in theory, because users should never be able create a self-invitation anyway.
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
