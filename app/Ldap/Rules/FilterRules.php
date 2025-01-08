<?php

namespace App\Ldap\Rules;

use App\Models\User;
use Illuminate\Database\Eloquent\Model as Eloquent;
use LdapRecord\Laravel\Auth\Rule;
use LdapRecord\Models\Model as LdapRecord;

class FilterRules implements Rule
{
    /**
     * Check if the rule passes validation.
     */
    public function passes(LdapRecord $user, ?Eloquent $model = null): bool
    {
        $filter = env('LDAP_FILTERS_ON', false);

        // No filter provided
        if ($filter === false) {
            return true;
        }

        if (!($model instanceof User)) {
            return false;
        }

        return match (env('LDAP_PROVIDER', 'openldap')) {
            'openldap' => \LdapRecord\Models\OpenLDAP\User::rawFilter($filter)->findByGuid($model->ldapguid) instanceof \LdapRecord\Models\OpenLDAP\User,
            'activedirectory' => \LdapRecord\Models\ActiveDirectory\User::rawFilter($filter)->findByGuid($model->ldapguid) instanceof \LdapRecord\Models\ActiveDirectory\User,
            'freeipa' => \LdapRecord\Models\FreeIPA\User::rawFilter($filter)->findByGuid($model->ldapguid) instanceof \LdapRecord\Models\FreeIPA\User,
            default => false, // this case should never happen
        };
    }
}
