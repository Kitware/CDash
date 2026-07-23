<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\Project;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class CreateRepositoryTest extends TestCase
{
    use CreatesProjects;

    use DatabaseTransactions;

    public function testCannotCreateRepositoryWhenProjectDoesNotExist(): void
    {
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->graphQL('
            mutation createRepository($input: CreateRepositoryInput!) {
                createRepository(input: $input) {
                    repository {
                        id
                    }
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => 123456789,
                'url' => fake()->url(),
                'username' => Str::uuid()->toString(),
                'password' => Str::uuid()->toString(),
                'branch' => Str::uuid()->toString(),
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertDatabaseEmpty(Repository::class);
    }

    public function testCannotCreateRepositoryAsAnonymousUser(): void
    {
        $project = $this->makePublicProject();

        $this->graphQL('
            mutation createRepository($input: CreateRepositoryInput!) {
                createRepository(input: $input) {
                    repository {
                        id
                    }
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'url' => fake()->url(),
                'username' => Str::uuid()->toString(),
                'password' => Str::uuid()->toString(),
                'branch' => Str::uuid()->toString(),
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertDatabaseEmpty(Repository::class);
    }

    public function testCannotCreateRepositoryAsNormalUser(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();

        $this->actingAs($user)->graphQL('
            mutation createRepository($input: CreateRepositoryInput!) {
                createRepository(input: $input) {
                    repository {
                        id
                    }
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'url' => fake()->url(),
                'username' => Str::uuid()->toString(),
                'password' => Str::uuid()->toString(),
                'branch' => Str::uuid()->toString(),
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertDatabaseEmpty(Repository::class);
    }

    public function testCannotCreateRepositoryAsNormalProjectUser(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();
        $project->users()->attach($user, ['role' => Project::PROJECT_USER]);

        $this->actingAs($user)->graphQL('
            mutation createRepository($input: CreateRepositoryInput!) {
                createRepository(input: $input) {
                    repository {
                        id
                    }
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'url' => fake()->url(),
                'username' => Str::uuid()->toString(),
                'password' => Str::uuid()->toString(),
                'branch' => Str::uuid()->toString(),
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertDatabaseEmpty(Repository::class);
    }

    public function testCanCreateRepositoryAsProjectAdmin(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();
        $project->users()->attach($user, ['role' => Project::PROJECT_ADMIN]);

        $url = fake()->url();
        $username = Str::uuid()->toString();
        $password = Str::uuid()->toString();
        $branch = Str::uuid()->toString();

        $response = $this->actingAs($user)->graphQL('
            mutation createRepository($input: CreateRepositoryInput!) {
                createRepository(input: $input) {
                    repository {
                        id
                        url
                        username
                        branch
                    }
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'url' => $url,
                'username' => $username,
                'password' => $password,
                'branch' => $branch,
            ],
        ]);

        self::assertDatabaseCount(Repository::class, 1);
        $repository = Repository::firstOrFail();
        self::assertSame($url, $repository->url);
        self::assertSame($username, $repository->username);
        self::assertSame($password, $repository->password);
        self::assertSame($branch, $repository->branch);

        $response->assertExactJson([
            'data' => [
                'createRepository' => [
                    'repository' => [
                        'id' => (string) $repository->id,
                        'url' => $url,
                        'username' => $username,
                        'branch' => $branch,
                    ],
                    'message' => null,
                ],
            ],
        ]);
    }

    public function testCanCreateRepositoryAsGlobalAdmin(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $url = fake()->url();
        $username = Str::uuid()->toString();
        $password = Str::uuid()->toString();
        $branch = Str::uuid()->toString();

        $response = $this->actingAs($user)->graphQL('
            mutation createRepository($input: CreateRepositoryInput!) {
                createRepository(input: $input) {
                    repository {
                        id
                        url
                        username
                        branch
                    }
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'url' => $url,
                'username' => $username,
                'password' => $password,
                'branch' => $branch,
            ],
        ]);

        self::assertDatabaseCount(Repository::class, 1);
        $repository = Repository::firstOrFail();
        self::assertSame($url, $repository->url);
        self::assertSame($username, $repository->username);
        self::assertSame($password, $repository->password);
        self::assertSame($branch, $repository->branch);

        $response->assertExactJson([
            'data' => [
                'createRepository' => [
                    'repository' => [
                        'id' => (string) $repository->id,
                        'url' => $url,
                        'username' => $username,
                        'branch' => $branch,
                    ],
                    'message' => null,
                ],
            ],
        ]);
    }
}
