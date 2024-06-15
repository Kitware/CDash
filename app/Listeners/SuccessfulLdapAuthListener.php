<?php

namespace App\Listeners;

use App\Models\User;
use App\Utils\LdapUtils;
use Illuminate\Auth\Events\Login;

/**
 * Sync project membership after login.
 */
class SuccessfulLdapAuthListener
{
    public function handle(Login $event): void
    {
        if (env('CDASH_AUTHENTICATION_PROVIDER', 'users') === 'ldap') {
            /**
             * @var User $user
             */
            $user = $event->user;
            if ($user === null) {
                abort(500, 'User does not exist.');
            }

            LdapUtils::syncUser($user);
        }
    }
}
