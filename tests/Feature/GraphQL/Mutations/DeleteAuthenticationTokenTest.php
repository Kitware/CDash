<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\AuthToken;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class DeleteAuthenticationTokenTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    public function testCannotDeleteMissingToken(): void
    {
        $user = $this->makeAdminUser();
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
        $user = $this->makeAdminUser();
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
        $user = $this->makeAdminUser();
        /** @var AuthToken $authToken */
        $authToken = $user->authenticationTokens()->save(AuthToken::factory()->make());

        self::assertDatabaseCount(AuthToken::class, 1);

        $this->actingAs($this->makeNormalUser())->graphQL('
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
        $user = $this->makeNormalUser();
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
        $user = $this->makeAdminUser();
        /** @var AuthToken $authToken */
        $authToken = $user->authenticationTokens()->save(AuthToken::factory()->make());

        self::assertDatabaseCount(AuthToken::class, 1);

        $this->actingAs($this->makeAdminUser())->graphQL('
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
        $user = $this->makeAdminUser();
        $project = $this->makePublicProject();
        /** @var AuthToken $authToken */
        $authToken = $user->authenticationTokens()->save(AuthToken::factory()->make([
            'scope' => 'submit_only',
            'projectid' => $project->id,
        ]));

        $projectUser = $this->makeNormalUser();
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
        $user = $this->makeAdminUser();
        $project = $this->makePublicProject();
        /** @var AuthToken $authToken */
        $authToken = $user->authenticationTokens()->save(AuthToken::factory()->make([
            'scope' => 'submit_only',
            'projectid' => $project->id,
        ]));

        $projectUser = $this->makeNormalUser();
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
