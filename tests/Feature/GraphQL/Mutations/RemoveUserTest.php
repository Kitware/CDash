<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\User;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class RemoveUserTest extends TestCase
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

        parent::tearDown();
    }

    public function testAdminCanDeleteUsers(): void
    {
        self::assertContains($this->users['normal']->id, User::pluck('id'));
        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!) {
                removeUser(input: {
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'userId' => $this->users['normal']->id,
        ])->assertJson([
            'data' => [
                'removeUser' => [
                    'message' => null,
                ],
            ],
        ], true);
        self::assertNotContains($this->users['normal']->id, User::pluck('id'));
    }

    public function testAdminCannotDeleteSelf(): void
    {
        self::assertContains($this->users['admin']->id, User::pluck('id'));
        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!) {
                removeUser(input: {
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'userId' => $this->users['admin']->id,
        ])->assertJson([
            'data' => [
                'removeUser' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);
        self::assertContains($this->users['normal']->id, User::pluck('id'));
    }

    public function testRegularUserCannotDeleteUsers(): void
    {
        self::assertContains($this->users['admin']->id, User::pluck('id'));
        $this->actingAs($this->users['normal'])->graphQL('
            mutation ($userId: ID!) {
                removeUser(input: {
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'userId' => $this->users['admin']->id,
        ])->assertJson([
            'data' => [
                'removeUser' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);
        self::assertContains($this->users['admin']->id, User::pluck('id'));
    }

    public function testAnonymousUserCannotDeleteUsers(): void
    {
        self::assertContains($this->users['admin']->id, User::pluck('id'));
        $this->graphQL('
            mutation ($userId: ID!) {
                removeUser(input: {
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'userId' => $this->users['admin']->id,
        ])->assertJson([
            'data' => [
                'removeUser' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ], true);
        self::assertContains($this->users['admin']->id, User::pluck('id'));
    }

    public function testAdminCannotDeleteMissingUser(): void
    {
        $this->actingAs($this->users['admin'])->graphQL('
            mutation ($userId: ID!) {
                removeUser(input: {
                    userId: $userId
                }) {
                    message
                }
            }
        ', [
            'userId' => 123456789,
        ])->assertJson([
            'data' => [
                'removeUser' => [
                    'message' => 'User does not exist.',
                ],
            ],
        ], true);
    }
}
