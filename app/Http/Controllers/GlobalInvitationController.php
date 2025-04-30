<?php

namespace App\Http\Controllers;

use App\Enums\GlobalRole;
use App\Models\GlobalInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

final class GlobalInvitationController extends AbstractController
{
    // We assume that this signed route has been verified already...
    public function __invoke(Request $request, int $invitationId): RedirectResponse
    {
        if (config('cdash.username_password_authentication_enabled') === false) {
            abort(401, 'Username and password registration is disabled.');
        }

        $user_invite = GlobalInvitation::find($invitationId);
        if ($user_invite === null) {
            abort(404, 'Invitation does not exist.');
        }

        if (User::where('email', $user_invite->email)->exists()) {
            $user_invite->deleteOrFail();
            abort(401, 'You are already registered.');
        }

        Auth::login(User::forceCreate([
            'admin' => $user_invite->role === GlobalRole::ADMINISTRATOR,
            'email' => $user_invite->email,
            'password' => $user_invite->password,
            'password_updated_at' => Carbon::now(),
        ]));

        $user_invite->deleteOrFail();

        return response()->redirectTo('/profile?password_expired=1');
    }
}
