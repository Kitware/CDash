<?php
declare(strict_types=1);

namespace App\Rules;

use Adldap\Laravel\Validation\Rules\Rule;

class LdapFilterRules extends Rule
{

    /**
     * Checks if the rule passes validation.
     *
     * @return bool
     */
    public function isValid()
    {
        $filter = env('LDAP_FILTERS_ON', false);
        $isValid = true;
        if ($filter) {
            $isValid = false;
            $user = $this->user;
            $connection = $user->getQuery()->getConnection();
            $result = $connection->search($user->getDn(), $filter, ['dn', 'cn', 'memberOf']);
            if (is_resource($result)) {
                $isValid = $connection->countEntries($result) > 0;
                $connection->freeResult($result);
            }
        }
        return $isValid;
    }
}
