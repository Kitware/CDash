<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class RemoveProjectUserTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    private Project $project;

    /**
     * @var array<User>
     */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->users = [];

        parent::tearDown();
    }

    private function addUserToProject(bool $admin = false): User
    {
        $user = $this->makeNormalUser();
        $this->users[] = $user;

        $this->project->users()->attach($user->id, [
            'role' => $admin ? Project::PROJECT_ADMIN : Project::PROJECT_USER,
        ]);

        return $user;
    }

    private function assertProjectMember(User $user): void
    {
        self::assertTrue($user->refresh()->exists());
        self::assertContains($user->id, $this->project->users()->pluck('id'));
    }

    private function assertNotProjectMember(User $user): void
    {
        self::assertTrue($user->refresh()->exists());
        self::assertNotContains($user->id, $this->project->users()->pluck('id'));
    }

    public function testAdminUserCanDeleteProjectMembers(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $userToDelete = $this->addUserToProject();

        $this->assertProjectMember($userToDelete);

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!) {
                removeProjectUser(input: {
                    projectId: $projectId
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project->id,
            'userId' => $userToDelete->id,
        ])->assertJson([
            'data' => [
                'removeProjectUser' => [
                    'message' => null,
                ],
            ],
        ], true);

        $this->assertNotProjectMember($userToDelete);
    }

    public function testNormalNonmemberUserCannotDeleteProjectMembers(): void
    {
        $this->users['normal'] = $this->makeNormalUser();
        $userToDelete = $this->addUserToProject();

        $this->assertProjectMember($userToDelete);

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!) {
                removeProjectUser(input: {
                    projectId: $projectId
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project->id,
            'userId' => $userToDelete->id,
        ])->assertJson([
            'data' => [
                'removeProjectUser' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);

        $this->assertProjectMember($userToDelete);
    }

    public function testAnonymousUserCannotDeleteProjectMembers(): void
    {
        $userToDelete = $this->addUserToProject();

        $this->assertProjectMember($userToDelete);

        $this->graphQL('
            mutation ($userId: ID!, $projectId: ID!) {
                removeProjectUser(input: {
                    projectId: $projectId
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project->id,
            'userId' => $userToDelete->id,
        ])->assertJson([
            'data' => [
                'removeProjectUser' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);

        $this->assertProjectMember($userToDelete);
    }

    public function testProjectAdminCanDeleteProjectMembers(): void
    {
        $projectAdmin = $this->addUserToProject(true);
        $userToDelete = $this->addUserToProject();

        $this->assertProjectMember($userToDelete);

        $this->actingAs($projectAdmin)->graphQL('
            mutation ($userId: ID!, $projectId: ID!) {
                removeProjectUser(input: {
                    projectId: $projectId
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project->id,
            'userId' => $userToDelete->id,
        ])->assertJson([
            'data' => [
                'removeProjectUser' => [
                    'message' => null,
                ],
            ],
        ], true);

        $this->assertNotProjectMember($userToDelete);
    }

    public function testProjectAdminCanDeleteSelf(): void
    {
        $projectAdmin = $this->addUserToProject(true);

        $this->assertProjectMember($projectAdmin);

        $this->actingAs($projectAdmin)->graphQL('
            mutation ($userId: ID!, $projectId: ID!) {
                removeProjectUser(input: {
                    projectId: $projectId
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project->id,
            'userId' => $projectAdmin->id,
        ])->assertJson([
            'data' => [
                'removeProjectUser' => [
                    'message' => null,
                ],
            ],
        ], true);

        $this->assertNotProjectMember($projectAdmin);
    }

    public function testRegularProjectUserCannotDeleteProjectMembers(): void
    {
        $projectUser = $this->addUserToProject();
        $userToDelete = $this->addUserToProject();

        $this->assertProjectMember($userToDelete);

        $this->actingAs($projectUser)->graphQL('
            mutation ($userId: ID!, $projectId: ID!) {
                removeProjectUser(input: {
                    projectId: $projectId
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project->id,
            'userId' => $userToDelete->id,
        ])->assertJson([
            'data' => [
                'removeProjectUser' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);

        $this->assertProjectMember($userToDelete);
    }

    public function testCannotDeleteNonmemberUser(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $this->users['normal'] = $this->makeNormalUser();

        self::assertTrue($this->users['normal']->refresh()->exists());

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!) {
                removeProjectUser(input: {
                    projectId: $projectId
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project->id,
            'userId' => $this->users['normal']->id,
        ])->assertJson([
            'data' => [
                'removeProjectUser' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);

        self::assertTrue($this->users['normal']->refresh()->exists());
    }

    public function testCannotDeleteMissingUser(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!) {
                removeProjectUser(input: {
                    projectId: $projectId
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project->id,
            'userId' => 123456789,
        ])->assertJson([
            'data' => [
                'removeProjectUser' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);
    }

    public function testHandlesMissingProject(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $this->users['normal'] = $this->makeNormalUser();

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!) {
                removeProjectUser(input: {
                    projectId: $projectId
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'projectId' => 123456789,
            'userId' => $this->users['normal']->id,
        ])->assertJson([
            'data' => [
                'removeProjectUser' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);
    }

    public function testCannotDeleteProjectMembersIfManagedByLdap(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $userToDelete = $this->addUserToProject();

        $this->assertProjectMember($userToDelete);

        Config::set('cdash.ldap_enabled', true);
        $this->project->ldapfilter = '(uid=*group_1*)';
        $this->project->save();

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!) {
                removeProjectUser(input: {
                    projectId: $projectId
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project->id,
            'userId' => $userToDelete->id,
        ])->assertJson([
            'data' => [
                'removeProjectUser' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);

        $this->assertProjectMember($userToDelete);
    }

    public function testCanDeleteProjectMemberWhenLdapEnabledButNoLdapFilter(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $userToDelete = $this->addUserToProject();

        $this->assertProjectMember($userToDelete);

        Config::set('cdash.ldap_enabled', true);

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!) {
                removeProjectUser(input: {
                    projectId: $projectId
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project->id,
            'userId' => $userToDelete->id,
        ])->assertJson([
            'data' => [
                'removeProjectUser' => [
                    'message' => null,
                ],
            ],
        ], true);

        $this->assertNotProjectMember($userToDelete);
    }
}
