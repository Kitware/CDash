<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\Project;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class DeleteRepositoryTest extends TestCase
{
    use CreatesProjects;

    use DatabaseTransactions;

    public function testCannotDeleteNonexistentRepository(): void
    {
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->graphQL('
            mutation deleteRepository($input: DeleteRepositoryInput!) {
                deleteRepository(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'repositoryId' => 123456789,
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertDatabaseEmpty(Repository::class);
    }

    public function testCannotDeleteRepositoryAsAnonymousUser(): void
    {
        $project = $this->makePublicProject();
        $repository = $project->repositories()->save(Repository::factory()->make());
        self::assertInstanceOf(Repository::class, $repository);

        self::assertDatabaseCount(Repository::class, 1);

        $this->graphQL('
            mutation deleteRepository($input: DeleteRepositoryInput!) {
                deleteRepository(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'repositoryId' => $repository->id,
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertDatabaseCount(Repository::class, 1);
    }

    public function testCannotDeleteRepositoryAsNormalUser(): void
    {
        $user = User::factory()->create();
        $project = $this->makePublicProject();
        $repository = $project->repositories()->save(Repository::factory()->make());
        self::assertInstanceOf(Repository::class, $repository);

        self::assertDatabaseCount(Repository::class, 1);

        $this->actingAs($user)->graphQL('
            mutation deleteRepository($input: DeleteRepositoryInput!) {
                deleteRepository(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'repositoryId' => $repository->id,
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertDatabaseCount(Repository::class, 1);
    }

    public function testCannotDeleteRepositoryAsNormalProjectUser(): void
    {
        $user = User::factory()->create();
        $project = $this->makePublicProject();
        $repository = $project->repositories()->save(Repository::factory()->make());
        $project->users()->attach($user, ['role' => Project::PROJECT_USER]);
        self::assertInstanceOf(Repository::class, $repository);

        self::assertDatabaseCount(Repository::class, 1);

        $this->actingAs($user)->graphQL('
            mutation deleteRepository($input: DeleteRepositoryInput!) {
                deleteRepository(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'repositoryId' => $repository->id,
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertDatabaseCount(Repository::class, 1);
    }

    public function testCanDeleteRepositoryAsProjectAdmin(): void
    {
        $user = User::factory()->create();
        $project = $this->makePublicProject();
        $repository = $project->repositories()->save(Repository::factory()->make());
        $project->users()->attach($user, ['role' => Project::PROJECT_ADMIN]);
        self::assertInstanceOf(Repository::class, $repository);

        self::assertDatabaseCount(Repository::class, 1);

        $this->actingAs($user)->graphQL('
            mutation deleteRepository($input: DeleteRepositoryInput!) {
                deleteRepository(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'repositoryId' => $repository->id,
            ],
        ])->assertExactJson([
            'data' => [
                'deleteRepository' => [
                    'message' => null,
                ],
            ],
        ]);

        self::assertDatabaseEmpty(Repository::class);
    }

    public function testCanDeleteRepositoryAsGlobalAdmin(): void
    {
        $user = User::factory()->adminUser()->create();
        $project = $this->makePublicProject();
        $repository = $project->repositories()->save(Repository::factory()->make());
        self::assertInstanceOf(Repository::class, $repository);

        self::assertDatabaseCount(Repository::class, 1);

        $this->actingAs($user)->graphQL('
            mutation deleteRepository($input: DeleteRepositoryInput!) {
                deleteRepository(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'repositoryId' => $repository->id,
            ],
        ])->assertExactJson([
            'data' => [
                'deleteRepository' => [
                    'message' => null,
                ],
            ],
        ]);

        self::assertDatabaseEmpty(Repository::class);
    }
}
