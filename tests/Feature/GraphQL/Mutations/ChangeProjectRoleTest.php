<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class ChangeProjectRoleTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    private Project $project;

    /**
     * @var array<User>
     */
    private array $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->users = [
            'admin' => $this->makeAdminUser(),
            'projectMember' => $this->makeNormalUser(),
            'projectAdmin' => $this->makeNormalUser(),
            'nonmemberUser' => $this->makeNormalUser(),
        ];

        $this->project
            ->users()
            ->attach($this->users['projectMember']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_USER,
            ]);

        $this->project
            ->users()
            ->attach($this->users['projectAdmin']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_ADMIN,
            ]);
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

    public function testAdminCanChangeRole(): void
    {
        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!, $role: ProjectRole!) {
                changeProjectRole(input: {
                    userId: $userId
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                    project {
                        id
                    }
                }
            }
        ', [
            'userId' => $this->users['projectMember']->id,
            'projectId' => $this->project->id,
            'role' => ProjectRole::ADMINISTRATOR,
        ])->assertExactJson([
            'data' => [
                'changeProjectRole' => [
                    'message' => null,
                    'user' => [
                        'id' => (string) $this->users['projectMember']->id,
                    ],
                    'project' => [
                        'id' => (string) $this->project->id,
                    ],
                ],
            ],
        ]);

        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->administrators()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));
    }

    public function testProjectAdminCanChangeRole(): void
    {
        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));

        $this->actingAs($this->users['projectAdmin'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!, $role: ProjectRole!) {
                changeProjectRole(input: {
                    userId: $userId
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                    project {
                        id
                    }
                }
            }
        ', [
            'userId' => $this->users['projectMember']->id,
            'projectId' => $this->project->id,
            'role' => ProjectRole::ADMINISTRATOR,
        ])->assertExactJson([
            'data' => [
                'changeProjectRole' => [
                    'message' => null,
                    'user' => [
                        'id' => (string) $this->users['projectMember']->id,
                    ],
                    'project' => [
                        'id' => (string) $this->project->id,
                    ],
                ],
            ],
        ]);

        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->administrators()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));
    }

    public function testProjectMemberCannotChangeRole(): void
    {
        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));

        $this->actingAs($this->users['projectMember'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!, $role: ProjectRole!) {
                changeProjectRole(input: {
                    userId: $userId
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                    project {
                        id
                    }
                }
            }
        ', [
            'userId' => $this->users['projectAdmin']->id,
            'projectId' => $this->project->id,
            'role' => ProjectRole::USER,
        ])->assertExactJson([
            'data' => [
                'changeProjectRole' => [
                    'message' => 'This action is unauthorized.',
                    'user' => null,
                    'project' => null,
                ],
            ],
        ]);

        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));
    }

    public function testProjectNonMemberCannotChangeRole(): void
    {
        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));

        $this->actingAs($this->users['nonmemberUser'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!, $role: ProjectRole!) {
                changeProjectRole(input: {
                    userId: $userId
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                    project {
                        id
                    }
                }
            }
        ', [
            'userId' => $this->users['projectMember']->id,
            'projectId' => $this->project->id,
            'role' => ProjectRole::ADMINISTRATOR,
        ])->assertExactJson([
            'data' => [
                'changeProjectRole' => [
                    'message' => 'This action is unauthorized.',
                    'user' => null,
                    'project' => null,
                ],
            ],
        ]);

        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));
    }

    public function testAdminCannotChangeOwnRole(): void
    {
        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!, $role: ProjectRole!) {
                changeProjectRole(input: {
                    userId: $userId
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                    project {
                        id
                    }
                }
            }
        ', [
            'userId' => $this->users['admin']->id,
            'projectId' => $this->project->id,
            'role' => ProjectRole::USER,
        ])->assertExactJson([
            'data' => [
                'changeProjectRole' => [
                    'message' => 'This action is unauthorized.',
                    'user' => null,
                    'project' => null,
                ],
            ],
        ]);

        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));
    }

    public function testAdminCannotChangeNonMemberRole(): void
    {
        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!, $role: ProjectRole!) {
                changeProjectRole(input: {
                    userId: $userId
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                    project {
                        id
                    }
                }
            }
        ', [
            'userId' => $this->users['nonmemberUser']->id,
            'projectId' => $this->project->id,
            'role' => ProjectRole::USER,
        ])->assertExactJson([
            'data' => [
                'changeProjectRole' => [
                    'message' => 'This action is unauthorized.',
                    'user' => null,
                    'project' => null,
                ],
            ],
        ]);

        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));
    }

    public function testAdminCannotInviteMissingUser(): void
    {
        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!, $role: ProjectRole!) {
                changeProjectRole(input: {
                    userId: $userId
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                    project {
                        id
                    }
                }
            }
        ', [
            'userId' => 12345678,
            'projectId' => $this->project->id,
            'role' => ProjectRole::USER,
        ])->assertExactJson([
            'data' => [
                'changeProjectRole' => [
                    'message' => 'Cannot change role for user which does not exist.',
                    'user' => null,
                    'project' => null,
                ],
            ],
        ]);

        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));
    }

    public function testAttemptToChangeRoleForUnknownProjectDoesNotRevealInfo(): void
    {
        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $projectId: ID!, $role: ProjectRole!) {
                changeProjectRole(input: {
                    userId: $userId
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                    project {
                        id
                    }
                }
            }
        ', [
            'userId' => $this->users['projectMember']->id,
            'projectId' => 12345678,
            'role' => ProjectRole::USER,
        ])->assertExactJson([
            'data' => [
                'changeProjectRole' => [
                    'message' => 'This action is unauthorized.',
                    'user' => null,
                    'project' => null,
                ],
            ],
        ]);

        self::assertNotContains($this->users['admin']->id, $this->project->users()->pluck('id'));
        self::assertContains($this->users['projectMember']->id, $this->project->basicUsers()->pluck('id'));
        self::assertContains($this->users['projectAdmin']->id, $this->project->administrators()->pluck('id'));
        self::assertNotContains($this->users['nonmemberUser']->id, $this->project->users()->pluck('id'));
    }
}
