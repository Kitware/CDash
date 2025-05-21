<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class ProjectInvitationTypeTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    private Project $project;
    private User $normalUser;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->normalUser = $this->makeNormalUser();
        $this->adminUser = $this->makeAdminUser();
    }

    protected function tearDown(): void
    {
        $this->project->delete();
        $this->normalUser->delete();
        $this->adminUser->delete();

        parent::tearDown();
    }

    public function testAdminCanViewInvitations(): void
    {
        $invitation = ProjectInvitation::create([
            'email' => fake()->unique()->email(),
            'invited_by_id' => $this->adminUser->id,
            'project_id' => $this->project->id,
            'role' => ProjectRole::USER,
            'invitation_timestamp' => Carbon::now(),
        ]);

        $this->actingAs($this->adminUser)->graphQL('
            query($id: ID) {
                project(id: $id) {
                    invitations {
                        edges {
                            node {
                                id
                                email
                                invitedBy {
                                    id
                                }
                                project {
                                    id
                                }
                                role
                                invitationTimestamp
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $this->project->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'invitations' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $invitation->id,
                                    'email' => $invitation->email,
                                    'invitedBy' => [
                                        'id' => (string) $this->adminUser->id,
                                    ],
                                    'project' => [
                                        'id' => (string) $this->project->id,
                                    ],
                                    'role' => 'USER',
                                    'invitationTimestamp' => $invitation->invitation_timestamp->toIso8601String(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testNormalUserCannotViewInvitations(): void
    {
        ProjectInvitation::create([
            'email' => fake()->unique()->email(),
            'invited_by_id' => $this->adminUser->id,
            'project_id' => $this->project->id,
            'role' => ProjectRole::USER,
            'invitation_timestamp' => Carbon::now(),
        ]);

        $this->actingAs($this->normalUser)->graphQL('
            query($id: ID) {
                project(id: $id) {
                    invitations {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $this->project->id,
        ])->assertGraphQLErrorMessage('This action is unauthorized.');
    }

    public function testAnonymousUserCannotViewInvitations(): void
    {
        ProjectInvitation::create([
            'email' => fake()->unique()->email(),
            'invited_by_id' => $this->adminUser->id,
            'project_id' => $this->project->id,
            'role' => ProjectRole::USER,
            'invitation_timestamp' => Carbon::now(),
        ]);

        $this->graphQL('
            query($id: ID) {
                project(id: $id) {
                    invitations {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $this->project->id,
        ])->assertGraphQLErrorMessage('This action is unauthorized.');
    }
}
