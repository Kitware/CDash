<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Tests in this file connect to a live LDAP server running in the development environment.
 */
class LdapIntegration extends TestCase
{
    protected function setUp() : void
    {
        // There isn't necessarily a one-to-one mapping between env vars and config values for LDAP
        putenv('LDAP_USERNAME=cn=admin,dc=example,dc=org');
        putenv('LDAP_PASSWORD=password');
        putenv('CDASH_AUTHENTICATION_PROVIDER=ldap');
        putenv('LDAP_PROVIDER=openldap');
        putenv('LDAP_HOST=ldap');
        putenv('LDAP_BASE_DN="dc=example,dc=org"');
        putenv('LDAP_LOGGING=true');
        putenv('LDAP_LOCATE_USERS_BY=uid');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Unset the env variables we set previously
        putenv('LDAP_USERNAME');
        putenv('LDAP_PASSWORD');
        putenv('CDASH_AUTHENTICATION_PROVIDER');
        putenv('LDAP_PROVIDER');
        putenv('LDAP_HOST');
        putenv('LDAP_BASE_DN');
        putenv('LDAP_LOGGING');
        putenv('LDAP_LOCATE_USERS_BY');

        parent::tearDown();
    }

    public function testLiveLdapLogin(): void
    {
        User::where('email', 'ldapuser01')->delete();

        // Just a brief sanity check to make sure sending incorrect data is blocked
        $this->post('/login', [
            'email' => 'ldapuser01',
            'password' => 'wrongpassword',
        ])->assertUnauthorized();

        // Username and password hardcoded in LDAP development container
        $this->post('/login', [
            'email' => 'ldapuser01',
            'password' => 'password1',
        ])->assertRedirect('/');

        // Ensure the user was actually created in the database, and then clean it up
        User::where(['email' => 'ldapuser01'])->firstOrFail()->delete();
    }

    public function testLiveLdapLoginWithFilters(): void
    {
        User::where('email', 'ldapuser01')->delete();
        User::where('email', 'ldapuser02')->delete();

        // Make sure we can log in with both users
        $this->post('/login', [
            'email' => 'ldapuser01',
            'password' => 'password1',
        ])->assertRedirect('/');
        $this->get('/logout')->assertRedirect('/');
        $this->post('/login', [
            'email' => 'ldapuser02',
            'password' => 'password2',
        ])->assertRedirect('/');
        $this->get('/logout')->assertRedirect('/');

        User::where(['email' => 'ldapuser01'])->firstOrFail()->delete();
        User::where(['email' => 'ldapuser02'])->firstOrFail()->delete();

        putenv('LDAP_FILTERS_ON=cn=ldapuser01');
        $this->post('/login', [
            'email' => 'ldapuser01',
            'password' => 'password1',
        ])->assertRedirect('/');
        $this->get('/logout')->assertRedirect('/');
        $this->post('/login', [
            'email' => 'ldapuser02',
            'password' => 'password2',
        ])->assertUnauthorized();

        // "Delete" the env variable
        putenv('LDAP_FILTERS_ON');

        User::where(['email' => 'ldapuser01'])->firstOrFail()->delete();
        self::assertCount(0, User::where(['email' => 'ldapuser02'])->get());
    }
}
