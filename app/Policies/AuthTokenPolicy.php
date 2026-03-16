<?php

namespace App\Policies;

use App\Models\User;

class AuthTokenPolicy
{
    public function viewAll(User $user): bool
    {
        return $user->admin;
    }
}
