<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Enums\GlobalRole;
use App\Models\GlobalInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class RevokeGlobalInvitationTest extends TestCase
{
    use CreatesUsers;

    /**
     * @var array<User>
     */
    private array $users;

    /**
     * @var array<GlobalInvitation>
     */
    private array $invitations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = [
            'admin' => $this->makeAdminUser(),
            'normal' => $this->makeNormalUser(),
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->users = [];

        foreach ($this->invitations as $invitation) {
            $invitation->delete();
        }
        $this->invitations = [];

        parent::tearDown();
    }

    public function testAdminCanDeleteInvitation(): void
    {
        $invitation = GlobalInvitation::create([
            'email' => fake()->unique()->email(),
            'invited_by_id' => $this->users['admin']->id,
            'role' => GlobalRole::USER,
            'invitation_timestamp' => Carbon::now(),
            'password' => Hash::make(Str::password()),
        ]);
        $this->invitations[] = $invitation;

        self::assertTrue($invitation->refresh()->exists());

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($invitationId: ID!) {
                revokeGlobalInvitation(input: {
                    invitationId: $invitationId
                }) {
                    message
                }
            }
        ', [
            'invitationId' => $invitation->id,
        ])->assertJson([
            'data' => [
                'revokeGlobalInvitation' => [
                    'message' => null,
                ],
            ],
        ], true);

        self::assertNull(GlobalInvitation::find($invitation->id));
    }

    public function testAnonymousUserCannotDeleteInvitation(): void
    {
        $invitation = GlobalInvitation::create([
            'email' => fake()->unique()->email(),
            'invited_by_id' => $this->users['admin']->id,
            'role' => GlobalRole::USER,
            'invitation_timestamp' => Carbon::now(),
            'password' => Hash::make(Str::password()),
        ]);
        $this->invitations[] = $invitation;

        self::assertTrue($invitation->refresh()->exists());

        $this->graphQL('
            mutation ($invitationId: ID!) {
                revokeGlobalInvitation(input: {
                    invitationId: $invitationId
                }) {
                    message
                }
            }
        ', [
            'invitationId' => $invitation->id,
        ])->assertJson([
            'data' => [
                'revokeGlobalInvitation' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);

        self::assertTrue($invitation->refresh()->exists());
    }

    public function testRegularUserCannotDeleteInvitation(): void
    {
        $invitation = GlobalInvitation::create([
            'email' => fake()->unique()->email(),
            'invited_by_id' => $this->users['admin']->id,
            'role' => GlobalRole::USER,
            'invitation_timestamp' => Carbon::now(),
            'password' => Hash::make(Str::password()),
        ]);
        $this->invitations[] = $invitation;

        self::assertTrue($invitation->refresh()->exists());

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($invitationId: ID!) {
                revokeGlobalInvitation(input: {
                    invitationId: $invitationId
                }) {
                    message
                }
            }
        ', [
            'invitationId' => $invitation->id,
        ])->assertJson([
            'data' => [
                'revokeGlobalInvitation' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);

        self::assertTrue($invitation->refresh()->exists());
    }

    public function testCannotDeleteMissingInvitation(): void
    {
        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($invitationId: ID!) {
                revokeGlobalInvitation(input: {
                    invitationId: $invitationId
                }) {
                    message
                }
            }
        ', [
            'invitationId' => 1234567,
        ])->assertJson([
            'data' => [
                'revokeGlobalInvitation' => [
                    'message' => 'Invitation does not exist.',
                ],
            ],
        ], true);
    }
}
