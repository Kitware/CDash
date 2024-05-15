<?php

namespace App\Ldap\Rules;

use Illuminate\Database\Eloquent\Model as Eloquent;
use LdapRecord\Laravel\Auth\Rule;
use LdapRecord\Models\Model as LdapRecord;

class FilterRules implements Rule
{
    /**
     * Check if the rule passes validation.
     */
    public function passes(LdapRecord $user, Eloquent $model = null): bool
    {
        $filter = env('LDAP_FILTERS_ON', false);

        // No filter provided
        if ($filter === false) {
            return true;
        }

        return count($user->getConnection()
            ->query()
            ->setDn($user->getDn())
            ->rawFilter($filter)
            ->get(['dn', 'cn'])) > 0;
    }
}
