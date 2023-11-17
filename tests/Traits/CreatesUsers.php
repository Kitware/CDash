<?php

namespace Tests\Traits;

use App\Models\User;

trait CreatesUsers
{
    public function makeAdminUser() : User
    {
        // Create an admin user.
        $admin = new User();
        $admin->firstname = 'Admin';
        $admin->lastname = 'User';
        $admin->email = 'admin@user';
        $admin->password = '45678';
        $admin->institution = 'me';
        $admin->admin = true;
        $admin->save();
        return $admin;
    }

    public function makeNormalUser() : User
    {
        // Create a non-administrator user.
        $user = new User();
        $user->firstname = 'Jane';
        $user->lastname = 'Smith';
        $user->email = 'jane@smith';
        $user->password = '12345';
        $user->institution = 'me';
        $user->admin = false;
        $user->save();
        return $user;
    }
}
