<?php

namespace Tests\Traits;

use App\Models\User;
use Illuminate\Support\Str;

trait CreatesUsers
{
    /**
     * Create an admin user.
     */
    public function makeAdminUser(
        ?string $firstname = null,
        ?string $lastname = null,
        ?string $email = null,
        ?string $password = null,
        ?string $institution = null,
    ): User {
        return $this->makeUser(
            $firstname ?? 'Admin',
            $lastname ?? 'User',
            $email ?? 'admin_' . Str::uuid()->toString() . '@example.com',
            $password ?? Str::uuid()->toString(),
            $institution ?? 'institution placeholder',
            true
        );
    }

    /**
     * Create a non-administrator user.
     */
    public function makeNormalUser(
        ?string $firstname = null,
        ?string $lastname = null,
        ?string $email = null,
        ?string $password = null,
        ?string $institution = null,
    ): User {
        return $this->makeUser(
            $firstname ?? 'Normal',
            $lastname ?? 'User',
            $email ?? 'user_' . Str::uuid()->toString() . '@example.com',
            $password ?? Str::uuid()->toString(),
            $institution ?? 'institution placeholder',
            false
        );
    }

    /**
     * Developers are encouraged to use the makeNormalUser() and makeAdminUser() helpers unless
     * special functionality is required.
     */
    public function makeUser(
        string $firstname,
        string $lastname,
        string $email,
        string $password,
        string $institution,
        bool $admin,
    ): User {
        $user = new User([
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'institution' => $institution,
        ]);
        $user->password = $password;
        $user->admin = $admin;
        $user->save();
        return $user;
    }
}
