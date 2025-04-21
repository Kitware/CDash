<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewEmail(User $currentUser, User $user): bool
    {
        // We can always see our own email.
        if ($currentUser->id === $user->id) {
            return true;
        }

        // Admins can see emails for everyone
        if ($currentUser->admin) {
            return true;
        }

        return false;
    }
}
