<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Enums\ProjectRole;
use App\Mail\InvitedToProject;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class InviteToProjectTest extends TestCase
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
            'normal' => $this->makeNormalUser(),
        ];
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

    /**
     * @return array<array<string>>
     */
    public static function roles(): array
    {
        $return_arr = [];
        foreach (ProjectRole::cases() as $role) {
            $return_arr[] = [$role->name];
        }
        return $return_arr;
    }

    /**
     * @dataProvider roles
     */
    public function testAdminCreatesInvitationCorrectly(string $role): void
    {
        Mail::fake();

        self::assertEmpty($this->project->invitations()->get());

        $email = fake()->email();

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $projectId: ID!, $role: ProjectRole!) {
                inviteToProject(input: {
                    email: $email
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    invitedUser {
                        email
                        invitedBy {
                            id
                        }
                        project {
                            id
                        }
                        role
                    }
                }
            }
        ', [
            'email' => $email,
            'projectId' => $this->project->id,
            'role' => $role,
        ])->assertJson([
            'data' => [
                'inviteToProject' => [
                    'message' => null,
                    'invitedUser' => [
                        'email' => $email,
                        'invitedBy' => [
                            'id' => (string) $this->users['admin']->id,
                        ],
                        'project' => [
                            'id' => (string) $this->project->id,
                        ],
                        'role' => $role,
                    ],
                ],
            ],
        ], true);

        self::assertCount(1, $this->project->invitations()->get());
        Mail::assertQueued(InvitedToProject::class);
    }

    public function testUserOutsideProjectCantCreateInvitation(): void
    {
        Mail::fake();

        self::assertEmpty($this->project->invitations()->get());

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($email: String!, $projectId: ID!, $role: ProjectRole!) {
                inviteToProject(input: {
                    email: $email
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    invitedUser {
                        id
                    }
                }
            }
        ', [
            'email' => fake()->email(),
            'projectId' => $this->project->id,
            'role' => 'USER',
        ])->assertJson([
            'data' => [
                'inviteToProject' => [
                    'message' => 'This action is unauthorized.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        self::assertEmpty($this->project->invitations()->get());
        Mail::assertNothingQueued();
    }

    public function testUserWithProjectUserRoleCantCreateInvitation(): void
    {
        Mail::fake();

        self::assertEmpty($this->project->invitations()->get());

        $this->project
            ->users()
            ->attach($this->users['normal']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_USER,
            ]);

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($email: String!, $projectId: ID!, $role: ProjectRole!) {
                inviteToProject(input: {
                    email: $email
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    invitedUser {
                        id
                    }
                }
            }
        ', [
            'email' => fake()->email(),
            'projectId' => $this->project->id,
            'role' => 'USER',
        ])->assertJson([
            'data' => [
                'inviteToProject' => [
                    'message' => 'This action is unauthorized.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        self::assertEmpty($this->project->invitations()->get());
        Mail::assertNothingQueued();
    }

    public function testUserWithProjectAdminRoleCanCreateInvitation(): void
    {
        Mail::fake();

        self::assertEmpty($this->project->invitations()->get());

        $email = fake()->email();

        $this->project
            ->users()
            ->attach($this->users['normal']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_ADMIN,
            ]);

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($email: String!, $projectId: ID!, $role: ProjectRole!) {
                inviteToProject(input: {
                    email: $email
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    invitedUser {
                        email
                    }
                }
            }
        ', [
            'email' => $email,
            'projectId' => $this->project->id,
            'role' => 'USER',
        ])->assertJson([
            'data' => [
                'inviteToProject' => [
                    'message' => null,
                    'invitedUser' => [
                        'email' => $email,
                    ],
                ],
            ],
        ], true);

        self::assertCount(1, $this->project->invitations()->get());
        Mail::assertQueued(InvitedToProject::class);
    }

    public function testUserWithProjectAdminRoleCannotCreateInvitationWhenConfigured(): void
    {
        Mail::fake();

        Config::set('cdash.project_admin_registration_form_enabled', false);

        self::assertEmpty($this->project->invitations()->get());

        $this->project
            ->users()
            ->attach($this->users['normal']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_ADMIN,
            ]);

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($email: String!, $projectId: ID!, $role: ProjectRole!) {
                inviteToProject(input: {
                    email: $email
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    invitedUser {
                        email
                    }
                }
            }
        ', [
            'email' => fake()->email(),
            'projectId' => $this->project->id,
            'role' => 'USER',
        ])->assertJson([
            'data' => [
                'inviteToProject' => [
                    'message' => 'This action is unauthorized.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        self::assertEmpty($this->project->invitations()->get());
        Mail::assertNothingQueued();
    }

    public function testCantCreateInvitationForMissingProject(): void
    {
        Mail::fake();

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $projectId: ID!, $role: ProjectRole!) {
                inviteToProject(input: {
                    email: $email
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    invitedUser {
                        id
                    }
                }
            }
        ', [
            'email' => fake()->email(),
            'projectId' => 1234567,
            'role' => 'USER',
        ])->assertJson([
            'data' => [
                'inviteToProject' => [
                    'message' => 'This action is unauthorized.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        Mail::assertNothingQueued();
    }

    public function testCantCreateDuplicateInvitation(): void
    {
        Mail::fake();

        self::assertEmpty($this->project->invitations()->get());

        $email = fake()->email();
        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $projectId: ID!, $role: ProjectRole!) {
                inviteToProject(input: {
                    email: $email
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    invitedUser {
                        email
                    }
                }
            }
        ', [
            'email' => $email,
            'projectId' => $this->project->id,
            'role' => 'USER',
        ])->assertJson([
            'data' => [
                'inviteToProject' => [
                    'message' => null,
                    'invitedUser' => [
                        'email' => $email,
                    ],
                ],
            ],
        ], true);

        self::assertCount(1, $this->project->invitations()->get());
        Mail::assertQueued(InvitedToProject::class, 1);

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $projectId: ID!, $role: ProjectRole!) {
                inviteToProject(input: {
                    email: $email
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    invitedUser {
                        email
                    }
                }
            }
        ', [
            'email' => $email,
            'projectId' => $this->project->id,
            'role' => 'USER',
        ])->assertJson([
            'data' => [
                'inviteToProject' => [
                    'message' => 'Duplicate invitations are not allowed.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        self::assertCount(1, $this->project->invitations()->get());
        Mail::assertQueued(InvitedToProject::class, 1);
    }

    /**
     * @return array<array<string>>
     */
    public static function invalidEmails(): array
    {
        return [
            ['notanemail'],
            ['example@example'],
            ['@example.com'],
            ['<script>@example.com'],
        ];
    }

    /**
     * @dataProvider invalidEmails
     */
    public function testCantCreateInvitationWithInvalidEmail(string $email): void
    {
        self::assertEmpty($this->project->invitations()->get());

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $projectId: ID!, $role: ProjectRole!) {
                inviteToProject(input: {
                    email: $email
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    invitedUser {
                        id
                    }
                }
            }
        ', [
            'email' => $email,
            'projectId' => $this->project->id,
            'role' => 'USER',
        ])->assertJson([
            'data' => [
                'inviteToProject' => [
                    'message' => 'The email must be a valid email address.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        self::assertEmpty($this->project->invitations()->get());
    }

    public function testCantCreateInvitationWhenUserWithEmailAlreadyExists(): void
    {
        self::assertEmpty($this->project->invitations()->get());

        $this->project
            ->users()
            ->attach($this->users['normal']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_USER,
            ]);

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $projectId: ID!, $role: ProjectRole!) {
                inviteToProject(input: {
                    email: $email
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    invitedUser {
                        id
                    }
                }
            }
        ', [
            'email' => $this->users['normal']->email,
            'projectId' => $this->project->id,
            'role' => 'USER',
        ])->assertJson([
            'data' => [
                'inviteToProject' => [
                    'message' => 'User is already a member of this project.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        self::assertEmpty($this->project->invitations()->get());
    }

    public function testCantCreateInvitationWhenMembersManagedByLdap(): void
    {
        Mail::fake();
        self::assertEmpty($this->project->invitations()->get());

        Config::set('cdash.ldap_enabled', true);
        $this->project->ldapfilter = '(uid=*group_1*)';
        $this->project->save();

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $projectId: ID!, $role: ProjectRole!) {
                inviteToProject(input: {
                    email: $email
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    invitedUser {
                        email
                    }
                }
            }
        ', [
            'email' => $this->users['normal']->email,
            'projectId' => $this->project->id,
            'role' => 'USER',
        ])->assertJson([
            'data' => [
                'inviteToProject' => [
                    'message' => 'This action is unauthorized.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        self::assertEmpty($this->project->invitations()->get());
        Mail::assertNothingQueued();
    }

    public function testCanCreateInvitationWhenLdapEnabledButNoProjectLdapFilter(): void
    {
        Mail::fake();
        self::assertEmpty($this->project->invitations()->get());

        Config::set('cdash.ldap_enabled', true);

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $projectId: ID!, $role: ProjectRole!) {
                inviteToProject(input: {
                    email: $email
                    projectId: $projectId
                    role: $role
                }) {
                    message
                    invitedUser {
                        email
                    }
                }
            }
        ', [
            'email' => $this->users['normal']->email,
            'projectId' => $this->project->id,
            'role' => 'USER',
        ])->assertJson([
            'data' => [
                'inviteToProject' => [
                    'message' => null,
                    'invitedUser' => [
                        'email' => $this->users['normal']->email,
                    ],
                ],
            ],
        ], true);

        self::assertCount(1, $this->project->invitations()->get());
        Mail::assertQueued(InvitedToProject::class);
    }
}
