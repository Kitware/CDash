<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewEmail(?User $currentUser, User $user): bool
    {
        // For now, anonymous users aren't able to see any emails.
        if ($currentUser === null) {
            return false;
        }

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

    public function changeRole(?User $currentUser, User $user): bool
    {
        if ($currentUser === null) {
            return false;
        }

        // Users can never change their own role.
        if ($currentUser->id === $user->id) {
            return false;
        }

        // Currently, only admins can change other users' role.
        if ($currentUser->admin) {
            return true;
        }

        return false;
    }

    public function delete(?User $currentUser, User $user): bool
    {
        if ($currentUser === null) {
            return false;
        }

        // Users can never remove themselves.
        if ($currentUser->id === $user->id) {
            return false;
        }

        // Currently, only admins can remove other users.
        if ($currentUser->admin) {
            return true;
        }

        return false;
    }
}
