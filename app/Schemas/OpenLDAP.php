<?php
namespace App\Schemas;
use Adldap\Schemas\OpenLDAP as BaseSchema;
class OpenLDAP extends BaseSchema
{
    /**
     * {@inheritdoc}
     */
    public function objectGuid()
    {
        return env('LDAP_GUID', '');
    }
}
