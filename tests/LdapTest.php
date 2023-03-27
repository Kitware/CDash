<?php

namespace Tests;

use Adldap\Connections\Ldap;
use Adldap\Laravel\Facades\Adldap;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * Parent class for LDAP tests.
 **/
abstract class LdapTest extends TestCase
{
    use WithFaker, DatabaseTransactions;

    protected function setUp() : void
    {
        // ensure that we're using LDAP, overriding whatever may be set in .env
        putenv('CDASH_AUTHENTICATION_PROVIDER=ldap');
        putenv('APP_URL=http://localhost');
        putenv('LDAP_LOGGING=false');
        parent::setUp();
    }

    protected function makeLdapUser(array $attributes = [])
    {
        $mock_ldap = $this->getMockBuilder(Ldap::class)->getMock();
        $provider = config('ldap_auth.connection');
        $user = Adldap::getProvider($provider)->make()->user($attributes);
        $user->getQuery()->setConnection($mock_ldap);
        return $user;
    }
}
