<?php

namespace App\Listeners;

use App\Models\User;
use LdapRecord\Laravel\Events\Auth\Completed;

/**
 * Only fired when the LDAP server accepted the user's LDAP password, rather than when a local
 * account logs in with its CDash password.  This happens before the user is logged in, so the
 * session and "remember me" cookie are created with the user's new random password.
 */
class RecordLdapLogin
{
    public function handle(Completed $event): void
    {
        if ($event->eloquent instanceof User) {
            $event->eloquent->recordExternalLogin();
        }
    }
}
