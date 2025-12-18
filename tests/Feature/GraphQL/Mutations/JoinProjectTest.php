<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\Project;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class JoinProjectTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;

    private ?Project $project = null;

    private ?User $user = null;

    protected function tearDown(): void
    {
        $this->project?->delete();
        $this->user?->delete();

        parent::tearDown();
    }

    public function testCanJoinPublicProject(): void
    {
        $this->project = $this->makePublicProject();
        $this->user = $this->makeNormalUser();

        self::assertEmpty($this->project->users()->get());

        $this->actingAs($this->user)->graphQL('
            mutation ($projectId: ID!) {
                joinProject(input: {
                    projectId: $projectId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project?->id,
        ])->assertExactJson([
            'data' => [
                'joinProject' => [
                    'message' => null,
                ],
            ],
        ]);

        self::assertContains($this->user?->id, $this->project?->users()->pluck('id') ?? []);
        self::assertCount(1, $this->project?->users()->get() ?? []);
    }

    public function testCanJoinProtectedProject(): void
    {
        $this->project = $this->makeProtectedProject();
        $this->user = $this->makeNormalUser();

        self::assertEmpty($this->project->users()->get());

        $this->actingAs($this->user)->graphQL('
            mutation ($projectId: ID!) {
                joinProject(input: {
                    projectId: $projectId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project?->id,
        ])->assertExactJson([
            'data' => [
                'joinProject' => [
                    'message' => null,
                ],
            ],
        ]);

        self::assertContains($this->user?->id, $this->project?->users()->pluck('id') ?? []);
        self::assertCount(1, $this->project?->users()->get() ?? []);
    }

    public function testCannotJoinPrivateProject(): void
    {
        $this->project = $this->makePrivateProject();
        $this->user = $this->makeNormalUser();

        self::assertEmpty($this->project->users()->get());

        $this->actingAs($this->user)->graphQL('
            mutation ($projectId: ID!) {
                joinProject(input: {
                    projectId: $projectId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project?->id,
        ])->assertExactJson([
            'data' => [
                'joinProject' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ]);

        self::assertEmpty($this->project?->users()->get());
    }

    public function testAnonymousUserCannotJoinProject(): void
    {
        $this->project = $this->makePublicProject();

        self::assertEmpty($this->project->users()->get());

        $this->graphQL('
            mutation ($projectId: ID!) {
                joinProject(input: {
                    projectId: $projectId
                }) {
                    message
                }
            }
        ', [
            'projectId' => $this->project->id,
        ])->assertExactJson([
            'data' => [
                'joinProject' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ]);

        self::assertEmpty($this->project->users()->get());
    }

    public function testCannotJoinMissingProject(): void
    {
        $this->user = $this->makeNormalUser();

        $this->actingAs($this->user)->graphQL('
            mutation ($projectId: ID!) {
                joinProject(input: {
                    projectId: $projectId
                }) {
                    message
                }
            }
        ', [
            'projectId' => 123456789,
        ])->assertExactJson([
            'data' => [
                'joinProject' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ]);
    }
}
