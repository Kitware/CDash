<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\AuthToken;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class DeleteAuthenticationTokenTest extends TestCase
{
    use CreatesProjects;

    use DatabaseTransactions;

    public function testCannotDeleteMissingToken(): void
    {
        $user = User::factory()->adminUser()->create();
        $user->authenticationTokens()->save(AuthToken::factory()->make());

        self::assertDatabaseCount(AuthToken::class, 1);

        $this->actingAs($user)->graphQL('
            mutation ($input: DeleteAuthenticationTokenInput!) {
                deleteAuthenticationToken(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'tokenId' => 123456789,
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertDatabaseCount(AuthToken::class, 1);
    }

    public function testAnonymousUserCannotDeleteToken(): void
    {
        $user = User::factory()->adminUser()->create();
        /** @var AuthToken $authToken */
        $authToken = $user->authenticationTokens()->save(AuthToken::factory()->make());

        self::assertDatabaseCount(AuthToken::class, 1);

        $this->graphQL('
            mutation ($input: DeleteAuthenticationTokenInput!) {
                deleteAuthenticationToken(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'tokenId' => $authToken->id,
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertDatabaseCount(AuthToken::class, 1);
    }

    public function testNormalUserCannotDeleteTokenOwnedByAnotherUser(): void
    {
        $user = User::factory()->adminUser()->create();
        /** @var AuthToken $authToken */
        $authToken = $user->authenticationTokens()->save(AuthToken::factory()->make());

        self::assertDatabaseCount(AuthToken::class, 1);

        $this->actingAs(User::factory()->create())->graphQL('
            mutation ($input: DeleteAuthenticationTokenInput!) {
                deleteAuthenticationToken(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'tokenId' => $authToken->id,
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertDatabaseCount(AuthToken::class, 1);
    }

    public function testNormalUserCanDeleteOwnTokens(): void
    {
        $user = User::factory()->create();
        /** @var AuthToken $authToken */
        $authToken = $user->authenticationTokens()->save(AuthToken::factory()->make());

        self::assertDatabaseCount(AuthToken::class, 1);

        $this->actingAs($user)->graphQL('
            mutation ($input: DeleteAuthenticationTokenInput!) {
                deleteAuthenticationToken(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'tokenId' => $authToken->id,
            ],
        ])->assertExactJson([
            'data' => [
                'deleteAuthenticationToken' => [
                    'message' => null,
                ],
            ],
        ]);

        self::assertDatabaseEmpty(AuthToken::class);
    }

    public function testAdminCanDeleteTokenOwnedByAnotherUser(): void
    {
        $user = User::factory()->adminUser()->create();
        /** @var AuthToken $authToken */
        $authToken = $user->authenticationTokens()->save(AuthToken::factory()->make());

        self::assertDatabaseCount(AuthToken::class, 1);

        $this->actingAs(User::factory()->adminUser()->create())->graphQL('
            mutation ($input: DeleteAuthenticationTokenInput!) {
                deleteAuthenticationToken(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'tokenId' => $authToken->id,
            ],
        ])->assertExactJson([
            'data' => [
                'deleteAuthenticationToken' => [
                    'message' => null,
                ],
            ],
        ]);

        self::assertDatabaseEmpty(AuthToken::class);
    }

    public function testProjectAdminCanDeleteProjectScopedTokenOwnedByAnotherUser(): void
    {
        $user = User::factory()->adminUser()->create();
        $project = $this->makePublicProject();
        /** @var AuthToken $authToken */
        $authToken = $user->authenticationTokens()->save(AuthToken::factory()->make([
            'scope' => 'submit_only',
            'projectid' => $project->id,
        ]));

        $projectUser = User::factory()->create();
        $project->users()->attach($projectUser, ['role' => Project::PROJECT_ADMIN]);

        self::assertDatabaseCount(AuthToken::class, 1);

        $this->actingAs($projectUser)->graphQL('
            mutation ($input: DeleteAuthenticationTokenInput!) {
                deleteAuthenticationToken(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'tokenId' => $authToken->id,
            ],
        ])->assertExactJson([
            'data' => [
                'deleteAuthenticationToken' => [
                    'message' => null,
                ],
            ],
        ]);

        self::assertDatabaseEmpty(AuthToken::class);
    }

    public function testNormalProjectUserCannotDeleteProjectScopedTokenOwnedByAnotherUser(): void
    {
        $user = User::factory()->adminUser()->create();
        $project = $this->makePublicProject();
        /** @var AuthToken $authToken */
        $authToken = $user->authenticationTokens()->save(AuthToken::factory()->make([
            'scope' => 'submit_only',
            'projectid' => $project->id,
        ]));

        $projectUser = User::factory()->create();
        $project->users()->attach($projectUser, ['role' => Project::PROJECT_USER]);

        self::assertDatabaseCount(AuthToken::class, 1);

        $this->actingAs($projectUser)->graphQL('
            mutation ($input: DeleteAuthenticationTokenInput!) {
                deleteAuthenticationToken(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'tokenId' => $authToken->id,
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertDatabaseCount(AuthToken::class, 1);
    }
}
