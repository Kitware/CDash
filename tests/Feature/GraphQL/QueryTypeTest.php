<?php

namespace Tests\Feature\GraphQL;

use App\Models\User;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class QueryTypeTest extends TestCase
{
    use CreatesUsers;

    /** @var array<User> */
    private array $users = [];

    protected function tearDown(): void
    {
        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->users = [];

        parent::tearDown();
    }

    public function testMeFieldWhenSignedIn(): void
    {
        $user = $this->makeNormalUser();
        $this->users[] = $user;

        $this->actingAs($user)->graphQL('
            query {
                me {
                    id
                }
            }
        ')->assertJson([
            'data' => [
                'me' => [
                    'id' => (string) $user->id,
                ],
            ],
        ], true);
    }

    public function testMeFieldWhenSignedOut(): void
    {
        $this->graphQL('
            query {
                me {
                    id
                }
            }
        ')->assertJson([
            'data' => [
                'me' => null,
            ],
        ], true);
    }

    public function testUserFieldInvalidUser(): void
    {
        $this->graphQL('
            query {
                user(
                    id: "123456789"
                ){
                    id
                }
            }
        ')->assertJson([
            'data' => [
                'user' => null,
            ],
        ], true);
    }

    public function testUserFieldValidUser(): void
    {
        $user = $this->makeNormalUser();
        $this->users[] = $user;

        $this->graphQL('
            query($userid: ID) {
                user(
                    id: $userid
                ){
                    id
                }
            }
        ', [
            'userid' => $user->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'id' => (string) $user->id,
                ],
            ],
        ], true);
    }

    public function testUsersFieldBasicAccess(): void
    {
        $user1 = $this->makeNormalUser();
        $user2 = $this->makeNormalUser();
        $this->users[] = $user1;
        $this->users[] = $user2;

        $this->graphQL('
            query($user1: ID, $user2: ID) {
                users(filters: {
                    any: [
                        {
                            eq: {
                                id: $user1
                            }
                        },
                        {
                            eq: {
                                id: $user2
                            }
                        }
                    ]
                }){
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        ', [
            'user1' => $user1->id,
            'user2' => $user2->id,
        ])->assertJson([
            'data' => [
                'users' => [
                    'edges' => [
                        [
                            'node' => [
                                'id' => (string) $user1->id,
                            ],
                        ],
                        [
                            'node' => [
                                'id' => (string) $user2->id,
                            ],
                        ],
                    ],
                ],
            ],
        ], true);
    }
}
