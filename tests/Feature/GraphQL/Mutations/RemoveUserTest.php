<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class RemoveUserTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

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
        ])->assertExactJson([
            'data' => [
                'removeUser' => [
                    'message' => null,
                ],
            ],
        ]);
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
        ])->assertExactJson([
            'data' => [
                'removeUser' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ]);
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
        ])->assertExactJson([
            'data' => [
                'removeUser' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ]);
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
        ])->assertExactJson([
            'data' => [
                'removeUser' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ]);
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
        ])->assertExactJson([
            'data' => [
                'removeUser' => [
                    'message' => 'User does not exist.',
                ],
            ],
        ]);
    }
}
