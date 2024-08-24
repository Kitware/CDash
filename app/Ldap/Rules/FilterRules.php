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

        if (!($model instanceof \App\Models\User)) {
            return false;
        }

        if (env('LDAP_PROVIDER', 'openldap') === 'activedirectory') {
            return \LdapRecord\Models\ActiveDirectory\User::rawFilter($filter)->findByGuid($model->ldapguid) instanceof \LdapRecord\Models\ActiveDirectory\User;
        } else {
            return  \LdapRecord\Models\OpenLDAP\User::rawFilter($filter)->findByGuid($model->ldapguid) instanceof \LdapRecord\Models\OpenLDAP\User;
        }
    }
}
