<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Enums\AuthTokenScope;
use App\Enums\ProjectRole;
use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{AuthTokenScope, bool, string, bool}>
     */
    public static function deletePermissionsCases(): array
    {
        $cases = [];
        foreach ([
            'full access' => [AuthTokenScope::FULL_ACCESS, false],
            'global submit-only' => [AuthTokenScope::SUBMIT_ONLY, false],
        ] as $name => [$scope, $projectScoped]) {
            $cases["{$name}, owner"] = [$scope, $projectScoped, 'owner', true];
            $cases["{$name}, system administrator"] = [$scope, $projectScoped, 'system administrator', true];
            $cases["{$name}, project administrator"] = [$scope, $projectScoped, 'project administrator', false];
            $cases["{$name}, project user"] = [$scope, $projectScoped, 'project user', false];
            $cases["{$name}, other user"] = [$scope, $projectScoped, 'other user', false];
        }

        $cases['project submit-only, owner'] = [AuthTokenScope::SUBMIT_ONLY, true, 'owner', true];
        $cases['project submit-only, system administrator'] = [AuthTokenScope::SUBMIT_ONLY, true, 'system administrator', true];
        $cases['project submit-only, project administrator'] = [AuthTokenScope::SUBMIT_ONLY, true, 'project administrator', true];
        $cases['project submit-only, project user'] = [AuthTokenScope::SUBMIT_ONLY, true, 'project user', false];
        $cases['project submit-only, other user'] = [AuthTokenScope::SUBMIT_ONLY, true, 'other user', false];

        return $cases;
    }

    #[DataProvider('deletePermissionsCases')]
    public function testDeletePermissions(AuthTokenScope $scope, bool $projectScoped, string $actor, bool $canDelete): void
    {
        $project = $this->makePublicProject();
        $owner = User::factory()->create();
        $project->users()->attach($owner, ['role' => ProjectRole::USER]);
        /** @var AuthToken $authToken */
        $authToken = $owner->authenticationTokens()->save(AuthToken::factory()->make([
            'scope' => $scope,
            'projectid' => $projectScoped ? $project->id : null,
        ]));

        $user = match ($actor) {
            'owner' => $owner,
            'system administrator' => User::factory()->adminUser()->create(),
            default => User::factory()->create(),
        };
        if ($actor === 'project administrator') {
            $project->users()->attach($user, ['role' => ProjectRole::ADMINISTRATOR]);
        } elseif ($actor === 'project user') {
            $project->users()->attach($user, ['role' => ProjectRole::USER]);
        }

        $response = $this->actingAs($user)->graphQL('
            mutation ($input: DeleteAuthenticationTokenInput!) {
                deleteAuthenticationToken(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'tokenId' => $authToken->id,
            ],
        ]);

        if ($canDelete) {
            $response->assertExactJson([
                'data' => [
                    'deleteAuthenticationToken' => [
                        'message' => null,
                    ],
                ],
            ]);
            self::assertModelMissing($authToken);
        } else {
            $response->assertGraphQLErrorMessage('This action is unauthorized.');
            self::assertModelExists($authToken);
        }
    }
}
