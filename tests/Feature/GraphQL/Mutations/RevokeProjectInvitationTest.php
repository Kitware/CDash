<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class RevokeProjectInvitationTest extends TestCase
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

    public function testAdminCanDeleteInvitation(): void
    {
        $invitation = ProjectInvitation::create([
            'email' => fake()->email(),
            'invited_by_id' => $this->users['admin']->id,
            'project_id' => $this->project->id,
            'role' => ProjectRole::USER,
            'invitation_timestamp' => Carbon::now(),
        ]);

        self::assertCount(1, $this->project->invitations()->get());

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($invitationId: ID!) {
                revokeProjectInvitation(input: {
                    invitationId: $invitationId
                }) {
                    message
                }
            }
        ', [
            'invitationId' => $invitation->id,
        ])->assertJson([
            'data' => [
                'revokeProjectInvitation' => [
                    'message' => null,
                ],
            ],
        ], true);

        self::assertEmpty($this->project->invitations()->get());
    }

    public function testAnonymousUserCannotDeleteInvitation(): void
    {
        $invitation = ProjectInvitation::create([
            'email' => fake()->email(),
            'invited_by_id' => $this->users['admin']->id,
            'project_id' => $this->project->id,
            'role' => ProjectRole::USER,
            'invitation_timestamp' => Carbon::now(),
        ]);

        self::assertCount(1, $this->project->invitations()->get());

        $this->graphQL('
            mutation ($invitationId: ID!) {
                revokeProjectInvitation(input: {
                    invitationId: $invitationId
                }) {
                    message
                }
            }
        ', [
            'invitationId' => $invitation->id,
        ])->assertJson([
            'data' => [
                'revokeProjectInvitation' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);

        self::assertCount(1, $this->project->invitations()->get());
    }

    public function testNonMemberUserCannotDeleteInvitation(): void
    {
        $invitation = ProjectInvitation::create([
            'email' => fake()->email(),
            'invited_by_id' => $this->users['admin']->id,
            'project_id' => $this->project->id,
            'role' => ProjectRole::USER,
            'invitation_timestamp' => Carbon::now(),
        ]);

        self::assertCount(1, $this->project->invitations()->get());

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($invitationId: ID!) {
                revokeProjectInvitation(input: {
                    invitationId: $invitationId
                }) {
                    message
                }
            }
        ', [
            'invitationId' => $invitation->id,
        ])->assertJson([
            'data' => [
                'revokeProjectInvitation' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);

        self::assertCount(1, $this->project->invitations()->get());
    }

    public function testMemberUserWithUserRoleCannotDeleteInvitation(): void
    {
        $invitation = ProjectInvitation::create([
            'email' => fake()->email(),
            'invited_by_id' => $this->users['admin']->id,
            'project_id' => $this->project->id,
            'role' => ProjectRole::USER,
            'invitation_timestamp' => Carbon::now(),
        ]);

        $this->project
            ->users()
            ->attach($this->users['normal']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_USER,
            ]);

        self::assertCount(1, $this->project->invitations()->get());

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($invitationId: ID!) {
                revokeProjectInvitation(input: {
                    invitationId: $invitationId
                }) {
                    message
                }
            }
        ', [
            'invitationId' => $invitation->id,
        ])->assertJson([
            'data' => [
                'revokeProjectInvitation' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);

        self::assertCount(1, $this->project->invitations()->get());
    }

    public function testMemberUserWithAdminRoleCanDeleteInvitation(): void
    {
        $invitation = ProjectInvitation::create([
            'email' => fake()->email(),
            'invited_by_id' => $this->users['admin']->id,
            'project_id' => $this->project->id,
            'role' => ProjectRole::USER,
            'invitation_timestamp' => Carbon::now(),
        ]);

        $this->project
            ->users()
            ->attach($this->users['normal']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_ADMIN,
            ]);

        self::assertCount(1, $this->project->invitations()->get());

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($invitationId: ID!) {
                revokeProjectInvitation(input: {
                    invitationId: $invitationId
                }) {
                    message
                }
            }
        ', [
            'invitationId' => $invitation->id,
        ])->assertJson([
            'data' => [
                'revokeProjectInvitation' => [
                    'message' => null,
                ],
            ],
        ], true);

        self::assertEmpty($this->project->invitations()->get());
    }

    public function testCannotDeleteMissingInvitation(): void
    {
        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($invitationId: ID!) {
                revokeProjectInvitation(input: {
                    invitationId: $invitationId
                }) {
                    message
                }
            }
        ', [
            'invitationId' => 1234567,
        ])->assertJson([
            'data' => [
                'revokeProjectInvitation' => [
                    'message' => 'Invitation does not exist.',
                ],
            ],
        ], true);
    }
}
