<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

/**
 * The AuthenticateSession middleware logs out sessions whose password hash no longer matches the
 * user's password.  When a user logs in, forget any hash left in the session by a previously
 * logged-in user (e.g., when an API token is used with an existing session) so that the
 * middleware stores the new user's hash instead of logging them out.
 */
class ForgetSessionPasswordHash
{
    public function handle(Login $event): void
    {
        session()->forget('password_hash_' . $event->guard);
    }
}
