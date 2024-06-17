<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use LdapRecord\Models\OpenLDAP\Group;
use LdapRecord\Models\OpenLDAP\User;
use Tests\TestCase;

/**
 * Tests in this file connect to a live LDAP server running in the development environment.
 */
class LdapIntegration extends TestCase
{
    protected Group $group_1;
    protected Group $group_2;

    /**
     * @var array<string,User>
     */
    protected array $users;

    /**
     * @throws \LdapRecord\LdapRecordException
     */
    protected function setUp(): void
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

        // Create two groups
        $this->group_1 = Group::create([
            'cn' => 'cdash_users_1_' . Str::uuid()->toString(),
            'uniqueMember' => 'ou=users,dc=example,dc=org',
        ]);

        $this->group_2 = Group::create([
            'cn' => 'cdash_users_2_' . Str::uuid()->toString(),
            'uniqueMember' => 'ou=users,dc=example,dc=org',
        ]);

        // Create a pair of users which are only in group 1
        $this->users['group_1_only_1'] = User::create([
            'uid' => Str::uuid()->toString(),
            'cn' => Str::uuid()->toString(),
            'userpassword' => Str::uuid()->toString(),
            'objectclass' => 'inetOrgPerson',
            'sn' => Str::uuid()->toString(),
        ]);
        $this->group_1->members()->attach($this->users['group_1_only_1']);

        $this->users['group_1_only_2'] = User::create([
            'uid' => Str::uuid()->toString(),
            'cn' => Str::uuid()->toString(),
            'userpassword' => Str::uuid()->toString(),
            'objectclass' => 'inetOrgPerson',
            'sn' => Str::uuid()->toString(),
        ]);
        $this->group_1->members()->attach($this->users['group_1_only_2']);

        // Create a pair of users which are only in group 2
        $this->users['group_2_only_1'] = User::create([
            'uid' => Str::uuid()->toString(),
            'cn' => Str::uuid()->toString(),
            'userpassword' => Str::uuid()->toString(),
            'objectclass' => 'inetOrgPerson',
            'sn' => Str::uuid()->toString(),
        ]);
        $this->group_2->members()->attach($this->users['group_2_only_1']);

        $this->users['group_2_only_2'] = User::create([
            'uid' => Str::uuid()->toString(),
            'cn' => Str::uuid()->toString(),
            'userpassword' => Str::uuid()->toString(),
            'objectclass' => 'inetOrgPerson',
            'sn' => Str::uuid()->toString(),
        ]);
        $this->group_2->members()->attach($this->users['group_2_only_2']);

        // Create a pair of users which are in both groups
        $this->users['groups_1_and_2_1'] = User::create([
            'uid' => Str::uuid()->toString(),
            'cn' => Str::uuid()->toString(),
            'userpassword' => Str::uuid()->toString(),
            'objectclass' => 'inetOrgPerson',
            'sn' => Str::uuid()->toString(),
        ]);
        $this->group_1->members()->attach($this->users['groups_1_and_2_1']);
        $this->group_2->members()->attach($this->users['groups_1_and_2_1']);

        $this->users['groups_1_and_2_2'] = User::create([
            'uid' => Str::uuid()->toString(),
            'cn' => Str::uuid()->toString(),
            'userpassword' => Str::uuid()->toString(),
            'objectclass' => 'inetOrgPerson',
            'sn' => Str::uuid()->toString(),
        ]);
        $this->group_1->members()->attach($this->users['groups_1_and_2_2']);
        $this->group_2->members()->attach($this->users['groups_1_and_2_2']);
    }

    /**
     * @throws \LdapRecord\LdapRecordException
     * @throws \LdapRecord\Models\ModelDoesNotExistException
     * @throws \Mockery\Exception\InvalidCountException
     */
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

        $this->group_1->delete(true);
        $this->group_2->delete(true);

        foreach ($this->users as $key => $user) {
            $user->delete(true);
        }
        $this->users = [];

        parent::tearDown();
    }

    public function testLdapConnection(): void
    {
        $this->artisan('ldap:test')
            ->assertSuccessful();
    }

    public function testLdapLogin(): void
    {
        $user = $this->users['group_1_only_1'];

        // Just a brief sanity check to make sure sending incorrect data is blocked
        $this->post('/login', [
            'email' => $user->getAttribute('uid')[0],
            'password' => 'wrongpassword',
        ])->assertUnauthorized();

        // Username and password hardcoded in LDAP development container
        $this->post('/login', [
            'email' => $user->getAttribute('uid')[0],
            'password' => $user->getAttribute('userpassword')[0],
        ])->assertRedirect('/');

        // Ensure the user was actually created in the database, and then clean it up
        \App\Models\User::where(['email' => $user->getAttribute('uid')[0]])->firstOrFail()->delete();
    }

    public function testLdapLoginWithFilters(): void
    {
        $user_group_1 = $this->users['group_1_only_1'];
        $user_group_2 = $this->users['group_2_only_1'];
        $user_all_groups = $this->users['groups_1_and_2_1'];

        self::assertEmpty(\App\Models\User::where('email', $user_group_1->getAttribute('uid')[0])->get());
        self::assertEmpty(\App\Models\User::where('email', $user_group_2->getAttribute('uid')[0])->get());
        self::assertEmpty(\App\Models\User::where('email', $user_all_groups->getAttribute('uid')[0])->get());

        // Make sure we can log in with all users
        $this->post('/login', [
            'email' => $user_group_1->getAttribute('uid')[0],
            'password' => $user_group_1->getAttribute('userpassword')[0],
        ])->assertRedirect('/');
        $this->get('/logout')->assertRedirect('/');

        $this->post('/login', [
            'email' => $user_group_2->getAttribute('uid')[0],
            'password' => $user_group_2->getAttribute('userpassword')[0],
        ])->assertRedirect('/');
        $this->get('/logout')->assertRedirect('/');

        $this->post('/login', [
            'email' => $user_all_groups->getAttribute('uid')[0],
            'password' => $user_all_groups->getAttribute('userpassword')[0],
        ])->assertRedirect('/');
        $this->get('/logout')->assertRedirect('/');

        \App\Models\User::where(['email' => $user_group_1->getAttribute('uid')[0]])->firstOrFail()->delete();
        \App\Models\User::where(['email' => $user_group_2->getAttribute('uid')[0]])->firstOrFail()->delete();
        \App\Models\User::where(['email' => $user_all_groups->getAttribute('uid')[0]])->firstOrFail()->delete();

        // Restrict login to members of group 1 only
        putenv("LDAP_FILTERS_ON=cn={$this->group_1->getAttribute('cn')[0]},dc=example,dc=org");
        $this->post('/login', [
            'email' => $user_group_1->getAttribute('uid')[0],
            'password' => $user_group_1->getAttribute('userpassword')[0],
        ])->assertRedirect('/');
        $this->get('/logout')->assertRedirect('/');

        $this->post('/login', [
            'email' => $user_group_2->getAttribute('uid')[0],
            'password' => $user_group_2->getAttribute('userpassword')[0],
        ])->assertUnauthorized();

        $this->post('/login', [
            'email' => $user_all_groups->getAttribute('uid')[0],
            'password' => $user_all_groups->getAttribute('userpassword')[0],
        ])->assertRedirect('/');
        $this->get('/logout')->assertRedirect('/');

        \App\Models\User::where(['email' => $user_group_1->getAttribute('uid')[0]])->firstOrFail()->delete();
        self::assertCount(0, User::where(['email' => $user_group_2->getAttribute('uid')[0]])->get());
        \App\Models\User::where(['email' => $user_all_groups->getAttribute('uid')[0]])->firstOrFail()->delete();

        // "Delete" the env variable
        putenv('LDAP_FILTERS_ON');
    }
}
