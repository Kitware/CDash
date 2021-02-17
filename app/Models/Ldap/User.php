<?php

namespace App\Models\Ldap;

use Adldap\Models\User as BaseModel;

class User extends BaseModel
{
    /**
     * Returns the model's GUID as is (not converted to binary).
     *
     * @return string|null
     */
    public function getConvertedGuid()
    {
        return $this->getObjectGuid();
    }
}
