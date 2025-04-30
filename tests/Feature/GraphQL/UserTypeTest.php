<?php

namespace Tests\Feature\GraphQL;

use App\Models\User;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class UserTypeTest extends TestCase
{
    use CreatesUsers;

    private User $normalUser;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalUser = $this->makeNormalUser();
        $this->adminUser = $this->makeAdminUser();
    }

    protected function tearDown(): void
    {
        $this->normalUser->delete();
        $this->adminUser->delete();

        parent::tearDown();
    }

    public function testBasicFieldAccess(): void
    {
        $this->actingAs($this->normalUser)->graphQL('
            query {
                me {
                    id
                    email
                    firstname
                    lastname
                    institution
                    admin
                }
            }
        ')->assertJson([
            'data' => [
                'me' => [
                    'id' => (string) $this->normalUser->id,
                    'email' => $this->normalUser->email,
                    'firstname' => $this->normalUser->firstname,
                    'lastname' => $this->normalUser->lastname,
                    'institution' => $this->normalUser->institution,
                    'admin' => $this->normalUser->admin,
                ],
            ],
        ], true);
    }

    public function testCanSeeOwnEmail(): void
    {
        $this->actingAs($this->normalUser)->graphQL('
            query($userid: ID) {
                user(id: $userid) {
                    id
                    email
                }
            }
        ', [
            'userid' => $this->normalUser->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'id' => (string) $this->normalUser->id,
                    'email' => $this->normalUser->email,
                ],
            ],
        ], true);
    }

    public function testCannotSeeEmailForOtherUsers(): void
    {
        $this->actingAs($this->normalUser)->graphQL('
            query($userid: ID) {
                user(id: $userid) {
                    id
                    email
                }
            }
        ', [
            'userid' => $this->adminUser->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'id' => (string) $this->adminUser->id,
                    'email' => null,
                ],
            ],
        ], true);
    }

    public function testAnonUsersCannotSeeEmails(): void
    {
        $this->graphQL('
            query($userid: ID) {
                user(id: $userid) {
                    id
                    email
                }
            }
        ', [
            'userid' => $this->normalUser->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'id' => (string) $this->normalUser->id,
                    'email' => null,
                ],
            ],
        ], true);
    }

    public function testAdminCanSeeAllEmails(): void
    {
        $this->actingAs($this->adminUser)->graphQL('
            query($userid: ID) {
                user(id: $userid) {
                    id
                    email
                }
            }
        ', [
            'userid' => $this->normalUser->id,
        ])->assertJson([
            'data' => [
                'user' => [
                    'id' => (string) $this->normalUser->id,
                    'email' => $this->normalUser->email,
                ],
            ],
        ], true);
    }
}
