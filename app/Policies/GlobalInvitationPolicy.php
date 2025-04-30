<?php

namespace App\Policies;

use App\Models\GlobalInvitation;
use App\Models\User;

class GlobalInvitationPolicy
{
    public function createInvitation(?User $currentUser): bool
    {
        if (config('cdash.username_password_authentication_enabled') === false) {
            return false;
        }

        if ($currentUser === null) {
            return false;
        }

        return $currentUser->admin;
    }

    public function revokeInvitation(?User $currentUser, GlobalInvitation $invitation): bool
    {
        if ($currentUser === null) {
            return false;
        }

        return $currentUser->admin;
    }
}
