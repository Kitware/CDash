<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Enums\GlobalRole;
use App\Mail\InvitedToCdash;
use App\Models\GlobalInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class CreateGlobalInvitationTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTruncation;

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

    /**
     * @return array<array<string>>
     */
    public static function roles(): array
    {
        $return_arr = [];
        foreach (GlobalRole::cases() as $role) {
            $return_arr[] = [$role->name];
        }
        return $return_arr;
    }

    #[DataProvider('roles')]
    public function testAdminCreatesInvitationCorrectly(string $role): void
    {
        Mail::fake();

        self::assertEmpty(GlobalInvitation::all());

        $email = fake()->unique()->email();

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $role: GlobalRole!) {
                createGlobalInvitation(input: {
                    email: $email
                    role: $role
                }) {
                    message
                    invitedUser {
                        email
                        invitedBy {
                            id
                        }
                        role
                    }
                }
            }
        ', [
            'email' => $email,
            'role' => $role,
        ])->assertJson([
            'data' => [
                'createGlobalInvitation' => [
                    'message' => null,
                    'invitedUser' => [
                        'email' => $email,
                        'invitedBy' => [
                            'id' => (string) $this->users['admin']->id,
                        ],
                        'role' => $role,
                    ],
                ],
            ],
        ], true);

        self::assertCount(1, GlobalInvitation::all());
        Mail::assertQueued(InvitedToCdash::class);
    }

    public function testRegularUserCantCreateInvitation(): void
    {
        Mail::fake();

        self::assertEmpty(GlobalInvitation::all());

        $email = fake()->unique()->email();

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($email: String!, $role: GlobalRole!) {
                createGlobalInvitation(input: {
                    email: $email
                    role: $role
                }) {
                    message
                    invitedUser {
                        email
                        invitedBy {
                            id
                        }
                        role
                    }
                }
            }
        ', [
            'email' => $email,
            'role' => GlobalRole::USER,
        ])->assertJson([
            'data' => [
                'createGlobalInvitation' => [
                    'message' => 'This action is unauthorized.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        self::assertEmpty(GlobalInvitation::all());
        Mail::assertNothingQueued();
    }

    public function testAnonymousUserCantCreateInvitation(): void
    {
        Mail::fake();

        self::assertEmpty(GlobalInvitation::all());

        $email = fake()->unique()->email();

        $this->graphQL('
            mutation ($email: String!, $role: GlobalRole!) {
                createGlobalInvitation(input: {
                    email: $email
                    role: $role
                }) {
                    message
                    invitedUser {
                        email
                        invitedBy {
                            id
                        }
                        role
                    }
                }
            }
        ', [
            'email' => $email,
            'role' => GlobalRole::USER,
        ])->assertJson([
            'data' => [
                'createGlobalInvitation' => [
                    'message' => 'Attempt to invite user when not signed in.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        self::assertEmpty(GlobalInvitation::all());
        Mail::assertNothingQueued();
    }

    public function testCantCreateDuplicateInvitation(): void
    {
        Mail::fake();

        self::assertEmpty(GlobalInvitation::all());

        $email = fake()->unique()->email();

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $role: GlobalRole!) {
                createGlobalInvitation(input: {
                    email: $email
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
            'role' => GlobalRole::USER,
        ])->assertJson([
            'data' => [
                'createGlobalInvitation' => [
                    'message' => null,
                    'invitedUser' => [
                        'email' => $email,
                    ],
                ],
            ],
        ], true);

        self::assertCount(1, GlobalInvitation::all());
        Mail::assertQueued(InvitedToCdash::class, 1);

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $role: GlobalRole!) {
                createGlobalInvitation(input: {
                    email: $email
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
            'role' => GlobalRole::USER,
        ])->assertJson([
            'data' => [
                'createGlobalInvitation' => [
                    'message' => 'Duplicate invitations are not allowed.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        self::assertCount(1, GlobalInvitation::all());
        Mail::assertQueued(InvitedToCdash::class, 1);
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

    #[DataProvider('invalidEmails')]
    public function testCantCreateInvitationWithInvalidEmail(string $email): void
    {
        Mail::fake();

        self::assertEmpty(GlobalInvitation::all());

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $role: GlobalRole!) {
                createGlobalInvitation(input: {
                    email: $email
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
            'role' => 'USER',
        ])->assertJson([
            'data' => [
                'createGlobalInvitation' => [
                    'message' => 'The email must be a valid email address.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        self::assertEmpty(GlobalInvitation::all());
        Mail::assertNothingQueued();
    }

    public function testCantCreateInvitationWhenUserWithEmailAlreadyExists(): void
    {
        Mail::fake();

        self::assertEmpty(GlobalInvitation::all());

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $role: GlobalRole!) {
                createGlobalInvitation(input: {
                    email: $email
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
            'role' => 'USER',
        ])->assertJson([
            'data' => [
                'createGlobalInvitation' => [
                    'message' => 'User is already a member of this instance.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        self::assertEmpty(GlobalInvitation::all());
        Mail::assertNothingQueued();
    }

    public function testCantCreateInvitationWhenPasswordAuthTurnedOff(): void
    {
        Config::set('cdash.username_password_authentication_enabled', false);

        Mail::fake();

        self::assertEmpty(GlobalInvitation::all());

        $email = fake()->unique()->email();

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($email: String!, $role: GlobalRole!) {
                createGlobalInvitation(input: {
                    email: $email
                    role: $role
                }) {
                    message
                    invitedUser {
                        email
                        invitedBy {
                            id
                        }
                        role
                    }
                }
            }
        ', [
            'email' => $email,
            'role' => GlobalRole::USER,
        ])->assertJson([
            'data' => [
                'createGlobalInvitation' => [
                    'message' => 'This action is unauthorized.',
                    'invitedUser' => null,
                ],
            ],
        ], true);

        self::assertEmpty(GlobalInvitation::all());
        Mail::assertNothingQueued();
    }
}
