<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Enums\ProjectRole;
use App\Jobs\DeleteProject as DeleteProjectJob;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class DeleteProjectTest extends TestCase
{
    use CreatesProjects;

    use DatabaseTransactions;

    public function testCannotDeleteNonexistentProject(): void
    {
        Queue::fake();

        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->graphQL('
            mutation deleteProject($input: DeleteProjectInput!) {
                deleteProject(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => 123456789,
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        Queue::assertNotPushed(DeleteProjectJob::class);
    }

    public function testCannotDeleteProjectAsAnonymousUser(): void
    {
        Queue::fake();

        $project = $this->makePublicProject();

        $this->graphQL('
            mutation deleteProject($input: DeleteProjectInput!) {
                deleteProject(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        Queue::assertNotPushed(DeleteProjectJob::class);
        self::assertDatabaseHas(Project::class, ['id' => $project->id]);
    }

    public function testCannotDeleteProjectAsNormalUser(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $project = $this->makePublicProject();

        $this->actingAs($user)->graphQL('
            mutation deleteProject($input: DeleteProjectInput!) {
                deleteProject(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        Queue::assertNotPushed(DeleteProjectJob::class);
        self::assertDatabaseHas(Project::class, ['id' => $project->id]);
    }

    public function testCannotDeleteProjectAsNormalProjectUser(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $project = $this->makePublicProject();
        $project->users()->attach($user, ['role' => ProjectRole::USER]);

        $this->actingAs($user)->graphQL('
            mutation deleteProject($input: DeleteProjectInput!) {
                deleteProject(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        Queue::assertNotPushed(DeleteProjectJob::class);
        self::assertDatabaseHas(Project::class, ['id' => $project->id]);
    }

    public function testCanDeleteProjectAsProjectAdmin(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $project = $this->makePublicProject();
        $project->users()->attach($user, ['role' => ProjectRole::ADMINISTRATOR]);

        $this->actingAs($user)->graphQL('
            mutation deleteProject($input: DeleteProjectInput!) {
                deleteProject(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
            ],
        ])->assertExactJson([
            'data' => [
                'deleteProject' => [
                    'message' => null,
                ],
            ],
        ]);

        Queue::assertPushed(DeleteProjectJob::class, fn ($job) => $job->projectId === $project->id);

        // Deletion happens in the (faked, non-executing) queued job, not synchronously.
        self::assertDatabaseHas(Project::class, ['id' => $project->id]);
    }

    public function testCanDeleteProjectAsGlobalAdmin(): void
    {
        Queue::fake();

        $user = User::factory()->adminUser()->create();
        $project = $this->makePublicProject();

        $this->actingAs($user)->graphQL('
            mutation deleteProject($input: DeleteProjectInput!) {
                deleteProject(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
            ],
        ])->assertExactJson([
            'data' => [
                'deleteProject' => [
                    'message' => null,
                ],
            ],
        ]);

        Queue::assertPushed(DeleteProjectJob::class, fn ($job) => $job->projectId === $project->id);
        self::assertDatabaseHas(Project::class, ['id' => $project->id]);
    }
}
