<?php

namespace Tests\Feature\GraphQL;

use App\Enums\GlobalRole;
use App\Models\GlobalInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class GlobalInvitationTypeTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTruncation;

    private User $normalUser;
    private User $adminUser;

    /** @var array<GlobalInvitation> */
    private array $invitations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalUser = $this->makeNormalUser();
        $this->adminUser = $this->makeAdminUser();
    }

    protected function tearDown(): void
    {
        foreach ($this->invitations as $invitation) {
            $invitation->delete();
        }
        $this->invitations = [];

        $this->normalUser->delete();
        $this->adminUser->delete();

        parent::tearDown();
    }

    private function createInvitation(): GlobalInvitation
    {
        /** @var GlobalInvitation $invitation */
        $invitation = GlobalInvitation::create([
            'email' => fake()->unique()->email(),
            'invited_by_id' => $this->adminUser->id,
            'role' => GlobalRole::USER,
            'invitation_timestamp' => Carbon::now(),
            'password' => Hash::make(Str::password()),
        ]);
        $this->invitations[] = $invitation;

        return $invitation;
    }

    public function testAdminCanViewInvitations(): void
    {
        $invitation = $this->createInvitation();

        $this->actingAs($this->adminUser)->graphQL('
            query {
                invitations {
                    edges {
                        node {
                            id
                            email
                            invitedBy {
                                id
                            }
                            role
                            invitationTimestamp
                        }
                    }
                }
            }
        ')->assertExactJson([
            'data' => [
                'invitations' => [
                    'edges' => [
                        [
                            'node' => [
                                'id' => (string) $invitation->id,
                                'email' => $invitation->email,
                                'invitedBy' => [
                                    'id' => (string) $this->adminUser->id,
                                ],
                                'role' => 'USER',
                                'invitationTimestamp' => $invitation->invitation_timestamp->toIso8601String(),
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testNormalUserCannotViewInvitations(): void
    {
        $this->createInvitation();
        $this->actingAs($this->normalUser)->graphQL('
            query {
                invitations {
                    edges {
                        node {
                            id
                            email
                            invitedBy {
                                id
                            }
                            role
                            invitationTimestamp
                        }
                    }
                }
            }
        ')->assertGraphQLErrorMessage('This action is unauthorized.');
    }

    public function testAnonymousUserCannotViewInvitations(): void
    {
        $this->createInvitation();
        $this->graphQL('
            query {
                invitations {
                    edges {
                        node {
                            id
                            email
                            invitedBy {
                                id
                            }
                            role
                            invitationTimestamp
                        }
                    }
                }
            }
        ')->assertGraphQLErrorMessage('This action is unauthorized.');
    }
}
