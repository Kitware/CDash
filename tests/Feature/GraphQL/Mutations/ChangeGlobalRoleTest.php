<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Enums\GlobalRole;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class ChangeGlobalRoleTest extends TestCase
{
    use CreatesUsers;

    /**
     * @var array<User>
     */
    private array $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = [
            'normal' => $this->makeNormalUser(),
            'admin' => $this->makeAdminUser(),
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->users = [];

        parent::tearDown();
    }

    public function testAdminCanChangeUserRole(): void
    {
        self::assertFalse($this->users['normal']->refresh()->admin);

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $role: GlobalRole!) {
                changeGlobalRole(input: {
                    userId: $userId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                }
            }
        ', [
            'userId' => $this->users['normal']->id,
            'role' => GlobalRole::ADMINISTRATOR,
        ])->assertExactJson([
            'data' => [
                'changeGlobalRole' => [
                    'message' => null,
                    'user' => [
                        'id' => (string) $this->users['normal']->id,
                    ],
                ],
            ],
        ]);

        self::assertTrue($this->users['normal']->refresh()->admin);

        // Change the user back to a normal user
        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $role: GlobalRole!) {
                changeGlobalRole(input: {
                    userId: $userId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                }
            }
        ', [
            'userId' => $this->users['normal']->id,
            'role' => GlobalRole::USER,
        ])->assertExactJson([
            'data' => [
                'changeGlobalRole' => [
                    'message' => null,
                    'user' => [
                        'id' => (string) $this->users['normal']->id,
                    ],
                ],
            ],
        ]);

        self::assertFalse($this->users['normal']->refresh()->admin);
    }

    public function testUserCannotChangeRole(): void
    {
        self::assertFalse($this->users['normal']->refresh()->admin);

        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($userId: ID!, $role: GlobalRole!) {
                changeGlobalRole(input: {
                    userId: $userId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                }
            }
        ', [
            'userId' => $this->users['admin']->id,
            'role' => GlobalRole::ADMINISTRATOR,
        ])->assertExactJson([
            'data' => [
                'changeGlobalRole' => [
                    'message' => 'Insufficient permissions.',
                    'user' => null,
                ],
            ],
        ]);

        self::assertFalse($this->users['normal']->refresh()->admin);
    }

    public function testAdminCannotChangeOwnRole(): void
    {
        self::assertTrue($this->users['admin']->refresh()->admin);

        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $role: GlobalRole!) {
                changeGlobalRole(input: {
                    userId: $userId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                }
            }
        ', [
            'userId' => $this->users['admin']->id,
            'role' => GlobalRole::USER,
        ])->assertExactJson([
            'data' => [
                'changeGlobalRole' => [
                    'message' => 'Insufficient permissions.',
                    'user' => null,
                ],
            ],
        ]);

        self::assertTrue($this->users['admin']->refresh()->admin);
    }

    public function testAdminCannotChangeRoleForMissingUser(): void
    {
        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!, $role: GlobalRole!) {
                changeGlobalRole(input: {
                    userId: $userId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                }
            }
        ', [
            'userId' => 123456789,
            'role' => GlobalRole::USER,
        ])->assertExactJson([
            'data' => [
                'changeGlobalRole' => [
                    'message' => 'Cannot change role for user which does not exist.',
                    'user' => null,
                ],
            ],
        ]);
    }

    public function testAnonymousUserCannotChangeRole(): void
    {
        self::assertTrue($this->users['admin']->refresh()->admin);

        $this->graphQL('
            mutation ($userId: ID!, $role: GlobalRole!) {
                changeGlobalRole(input: {
                    userId: $userId
                    role: $role
                }) {
                    message
                    user {
                        id
                    }
                }
            }
        ', [
            'userId' => $this->users['admin']->id,
            'role' => GlobalRole::USER,
        ])->assertExactJson([
            'data' => [
                'changeGlobalRole' => [
                    'message' => 'Attempt to invite user when not signed in.',
                    'user' => null,
                ],
            ],
        ]);

        self::assertTrue($this->users['admin']->refresh()->admin);
    }
}
