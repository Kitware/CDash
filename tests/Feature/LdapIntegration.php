<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ModelDoesNotExistException;
use LdapRecord\Models\OpenLDAP\Group;
use LdapRecord\Models\OpenLDAP\User;
use Mockery\Exception\InvalidCountException;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

/**
 * Tests in this file connect to a live LDAP server running in the development environment.
 */
class LdapIntegration extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;

    /**
     * @var array<string,User>
     */
    protected array $users;

    /**
     * @var array<\App\Models\User>
     */
    protected array $database_users = [];

    /**
     * @var array<string,Project>
     */
    protected array $projects;

    /**
     * @throws LdapRecordException
     */
    protected function setUp(): void
    {
        // There isn't necessarily a one-to-one mapping between env vars and config values for LDAP
        putenv('LDAP_USERNAME=cn=admin,dc=example,dc=org');
        putenv('LDAP_PASSWORD=password');
        putenv('CDASH_AUTHENTICATION_PROVIDER=ldap');
        putenv('LDAP_PROVIDER=openldap');
        putenv('LDAP_HOSTS=ldap');
        putenv('LDAP_BASE_DN="dc=example,dc=org"');
        putenv('LDAP_LOGGING=true');
        putenv('LDAP_LOCATE_USERS_BY=uid');

        parent::setUp();

        // Create a pair of users which are only in group 1
        $this->users['group_1_only_1'] = User::create([
            'uid' => 'group_1_' . Str::uuid()->toString(),
            'cn' => Str::uuid()->toString(),
            'userpassword' => Str::uuid()->toString(),
            'objectclass' => 'inetOrgPerson',
            'sn' => Str::uuid()->toString(),
        ]);

        $this->users['group_1_only_2'] = User::create([
            'uid' => 'group_1_' . Str::uuid()->toString(),
            'cn' => Str::uuid()->toString(),
            'userpassword' => Str::uuid()->toString(),
            'objectclass' => 'inetOrgPerson',
            'sn' => Str::uuid()->toString(),
        ]);

        // Create a pair of users which are only in group 2
        $this->users['group_2_only_1'] = User::create([
            'uid' => 'group_2_' . Str::uuid()->toString(),
            'cn' => Str::uuid()->toString(),
            'userpassword' => Str::uuid()->toString(),
            'objectclass' => 'inetOrgPerson',
            'sn' => Str::uuid()->toString(),
        ]);

        $this->users['group_2_only_2'] = User::create([
            'uid' => 'group_2_' . Str::uuid()->toString(),
            'cn' => Str::uuid()->toString(),
            'userpassword' => Str::uuid()->toString(),
            'objectclass' => 'inetOrgPerson',
            'sn' => Str::uuid()->toString(),
        ]);

        // Create a pair of users which are in both groups
        $this->users['groups_1_and_2_1'] = User::create([
            'uid' => 'group_1_group_2_' . Str::uuid()->toString(),
            'cn' => Str::uuid()->toString(),
            'userpassword' => Str::uuid()->toString(),
            'objectclass' => 'inetOrgPerson',
            'sn' => Str::uuid()->toString(),
        ]);

        $this->users['groups_1_and_2_2'] = User::create([
            'uid' => 'group_1_group_2_' . Str::uuid()->toString(),
            'cn' => Str::uuid()->toString(),
            'userpassword' => Str::uuid()->toString(),
            'objectclass' => 'inetOrgPerson',
            'sn' => Str::uuid()->toString(),
        ]);

        // Create a pair of projects which are restricted to specific LDAP groups
        $this->projects['only_group_1'] = $this->makePrivateProject();
        $this->projects['only_group_1']->ldapfilter = '(uid=*group_1*)';
        $this->projects['only_group_1']->save();

        $this->projects['only_group_2'] = $this->makePrivateProject();
        $this->projects['only_group_2']->ldapfilter = '(uid=*group_2*)';
        $this->projects['only_group_2']->save();
    }

    /**
     * @throws LdapRecordException
     * @throws ModelDoesNotExistException
     * @throws InvalidCountException
     */
    protected function tearDown(): void
    {
        // Unset the env variables we set previously
        putenv('LDAP_USERNAME');
        putenv('LDAP_PASSWORD');
        putenv('CDASH_AUTHENTICATION_PROVIDER');
        putenv('LDAP_PROVIDER');
        putenv('LDAP_HOSTS');
        putenv('LDAP_BASE_DN');
        putenv('LDAP_LOGGING');
        putenv('LDAP_LOCATE_USERS_BY');

        foreach ($this->users as $key => $user) {
            $user->delete(true);
        }
        $this->users = [];

        foreach ($this->database_users as $key => $user) {
            $user->delete();
        }
        $this->database_users = [];

        foreach ($this->projects as $key => $project) {
            $project->delete();
        }
        $this->projects = [];

        parent::tearDown();
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

    public function testLoginWithFilters(): void
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
        putenv('LDAP_FILTERS_ON=(uid=*group_1*)');
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

        // Restrict login to both groups
        putenv('LDAP_FILTERS_ON=(|(uid=*group_1*)(uid=*group_2*))');

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

        // "Delete" the env variable
        putenv('LDAP_FILTERS_ON');
    }

    public function testArtisanSyncsProjectMembership(): void
    {
        // Create the user in the database
        $this->post('/login', [
            'email' => $this->users['group_1_only_1']->getAttribute('uid')[0],
            'password' => $this->users['group_1_only_1']->getAttribute('userpassword')[0],
        ])->assertRedirect('/');

        $user = \App\Models\User::where(['email' => $this->users['group_1_only_1']->getAttribute('uid')[0]])->firstOrFail();

        // A brief sanity check...
        self::assertNotContains($user->email, $this->projects['only_group_2']->users()->pluck('email'));

        // Use a project which didn't have this user as a member when the initial login occurred
        $this->projects['only_group_2']->ldapfilter = '(uid=*group_1*)';
        $this->projects['only_group_2']->save();
        $this->artisan('ldap:sync_projects');
        self::assertContains($user->email, $this->projects['only_group_2']->users()->pluck('email'));

        // Change the group, and verify that the user was removed from the project
        $this->projects['only_group_2']->ldapfilter = '(uid=*group_2*)';
        $this->projects['only_group_2']->save();
        $this->artisan('ldap:sync_projects');
        self::assertNotContains($user->email, $this->projects['only_group_2']->users()->pluck('email'));
    }

    public function testArtisanSyncsGuid(): void
    {
        $user = $this->makeNormalUser();
        $user->email = $this->users['group_1_only_1']->getAttribute('uid')[0];
        $user->save();
        $this->database_users[] = $user;

        self::assertNull($user->refresh()->ldapguid);
        $this->projectAccessAssertions('group_1_only_1', 'only_group_1', false);
        $this->projectAccessAssertions('group_1_only_1', 'only_group_2', false);

        $this->artisan('ldap:sync_projects');

        self::assertNotNull($user->refresh()->ldapguid);
        $this->projectAccessAssertions('group_1_only_1', 'only_group_1', true);
        $this->projectAccessAssertions('group_1_only_1', 'only_group_2', false);
    }

    protected function projectAccessAssertions(string $user, string $project, bool $should_access): void
    {
        $user = \App\Models\User::where(['email' => $this->users[$user]->getAttribute('uid')[0]])->firstOrFail();
        $project = $this->projects[$project];

        $result = $this->actingAs($user)->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    name
                }
            }
        ', [
            'id' => $project->id,
        ]);

        if ($should_access) {
            $result->assertJson([
                'data' => [
                    'project' => [
                        'name' => $project->name,
                    ],
                ],
            ], true);
        } else {
            $result->assertJson([
                'data' => [
                    'project' => null,
                ],
            ], true);
        }

        $this->get('/logout')->assertRedirect('/');
    }

    protected function assertCanAccessProject(string $user, string $project): void
    {
        $this->projectAccessAssertions($user, $project, true);
    }

    protected function assertCannotAccessProject(string $user, string $project): void
    {
        $this->projectAccessAssertions($user, $project, false);
    }

    public function testGroupBasedProjectMembership(): void
    {
        // Make sure the users exist in the database
        $this->post('/login', [
            'email' => $this->users['group_1_only_1']->getAttribute('uid')[0],
            'password' => $this->users['group_1_only_1']->getAttribute('userpassword')[0],
        ])->assertRedirect('/');
        $this->get('/logout')->assertRedirect('/');

        $this->post('/login', [
            'email' => $this->users['group_2_only_1']->getAttribute('uid')[0],
            'password' => $this->users['group_2_only_1']->getAttribute('userpassword')[0],
        ])->assertRedirect('/');
        $this->get('/logout')->assertRedirect('/');

        $this->post('/login', [
            'email' => $this->users['groups_1_and_2_1']->getAttribute('uid')[0],
            'password' => $this->users['groups_1_and_2_1']->getAttribute('userpassword')[0],
        ])->assertRedirect('/');
        $this->get('/logout')->assertRedirect('/');

        $this->artisan('ldap:sync_projects');

        // Test basic membership controls
        $this->assertCanAccessProject('group_1_only_1', 'only_group_1');
        $this->assertCannotAccessProject('group_1_only_1', 'only_group_2');
        $this->assertCannotAccessProject('group_2_only_1', 'only_group_1');
        $this->assertCanAccessProject('group_2_only_1', 'only_group_2');
        $this->assertCanAccessProject('groups_1_and_2_1', 'only_group_1');
        $this->assertCanAccessProject('groups_1_and_2_1', 'only_group_2');

        // Make sure the membership is removed when the LDAP group rule is changed
        $this->projects['only_group_2']->ldapfilter = '(uid=*group_1*)';
        $this->projects['only_group_2']->save();

        $this->artisan('ldap:sync_projects');

        $this->assertCanAccessProject('group_1_only_1', 'only_group_1');
        $this->assertCanAccessProject('group_1_only_1', 'only_group_2');
        $this->assertCannotAccessProject('group_2_only_1', 'only_group_1');
        $this->assertCannotAccessProject('group_2_only_1', 'only_group_2');
        $this->assertCanAccessProject('groups_1_and_2_1', 'only_group_1');
        $this->assertCanAccessProject('groups_1_and_2_1', 'only_group_2');
    }

    public function testSyncsGroupsUponLogin(): void
    {
        $this->post('/login', [
            'email' => $this->users['group_1_only_1']->getAttribute('uid')[0],
            'password' => $this->users['group_1_only_1']->getAttribute('userpassword')[0],
        ])->assertRedirect('/');

        // The user and associated project membership links were created by logging in
        $this->assertCanAccessProject('group_1_only_1', 'only_group_1');

        // Basic sanity check...
        $this->assertCannotAccessProject('group_1_only_1', 'only_group_2');

        $this->projects['only_group_2']->ldapfilter = '(uid=*group_1*)';
        $this->projects['only_group_2']->save();

        // Still can't access the project because the link hasn't been created yet
        $this->assertCannotAccessProject('group_1_only_1', 'only_group_2');

        // Logging in should reset the links
        $this->post('/login', [
            'email' => $this->users['group_1_only_1']->getAttribute('uid')[0],
            'password' => $this->users['group_1_only_1']->getAttribute('userpassword')[0],
        ])->assertRedirect('/');

        $this->assertCanAccessProject('group_1_only_1', 'only_group_1');
        $this->assertCanAccessProject('group_1_only_1', 'only_group_2');
    }
}
