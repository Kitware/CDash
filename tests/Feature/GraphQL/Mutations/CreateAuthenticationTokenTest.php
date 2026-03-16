<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\AuthToken;
use App\Models\Project;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class CreateAuthenticationTokenTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    public function testCannotCreateProjectSpecificSubmitOnlyAuthTokenWhenProjectDoesNotExist(): void
    {
        $user = $this->makeAdminUser();

        $this->actingAs($user)->graphQL('
            mutation ($input: CreateAuthenticationTokenInput!) {
                createAuthenticationToken(input: $input) {
                    rawToken
                    token {
                        id
                    }
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => 123456789,
                'scope' => 'SUBMIT_ONLY',
                'expiration' => Carbon::now()->addDay()->toIso8601String(),
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertDatabaseEmpty(AuthToken::class);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function projectScopedSubmitOnlyTokenCreationPermissionsCases(): array
    {
        return [
            [null, null, false],
            ['normal', null, false],
            ['normal', Project::PROJECT_USER, true],
            ['normal', Project::PROJECT_ADMIN, true],
            ['admin', Project::PROJECT_USER, true],
            ['admin', Project::PROJECT_ADMIN, true],
            ['admin', null, true],
        ];
    }

    #[DataProvider('projectScopedSubmitOnlyTokenCreationPermissionsCases')]
    public function testProjectScopedSubmitOnlyTokenCreationPermissions(
        ?string $user,
        ?int $projectRole,
        bool $canCreateAuthToken,
    ): void {
        $project = $this->makePublicProject();

        if ($user === 'normal') {
            $user = $this->makeNormalUser();
        } elseif ($user === 'admin') {
            $user = $this->makeAdminUser();
        } elseif ($user !== null) {
            throw new Exception('Invalid user provided.');
        }

        if ($projectRole !== null) {
            $project->users()->attach($user, ['role' => $projectRole]);
        }

        self::assertDatabaseEmpty(AuthToken::class);

        $response = ($user === null ? $this : $this->actingAs($user))->graphQL('
            mutation ($input: CreateAuthenticationTokenInput!) {
                createAuthenticationToken(input: $input) {
                    token {
                        project {
                            id
                        }
                    }
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'scope' => 'SUBMIT_ONLY',
                'expiration' => Carbon::now()->addDay()->toIso8601String(),
            ],
        ]);

        if ($canCreateAuthToken) {
            $response->assertExactJson([
                'data' => [
                    'createAuthenticationToken' => [
                        'token' => [
                            'project' => [
                                'id' => (string) $project->id,
                            ],
                        ],
                        'message' => null,
                    ],
                ],
            ]);
            self::assertDatabaseHas(AuthToken::class, [
                'projectid' => $project->id,
                'userid' => $user?->id,
                'scope' => 'submit_only',
            ]);
        } else {
            $response->assertGraphQLErrorMessage('This action is unauthorized.');
            self::assertDatabaseEmpty(AuthToken::class);
        }
    }

    /**
     * @return array<array<mixed>>
     */
    public static function fullAccessAndGlobalSubmitOnlyTokenCreationPermissionsCases(): array
    {
        return [
            [null, 'full_access', false],
            [null, 'submit_only', false],
            ['normal', 'full_access', true],
            ['normal', 'submit_only', true],
            ['admin', 'full_access', true],
            ['admin', 'submit_only', true],
        ];
    }

    #[DataProvider('fullAccessAndGlobalSubmitOnlyTokenCreationPermissionsCases')]
    public function testFullAccessAndGlobalSubmitOnlyTokenCreationPermissions(
        ?string $user,
        string $scope,
        bool $canCreateAuthToken,
    ): void {
        if ($user === 'normal') {
            $user = $this->makeNormalUser();
        } elseif ($user === 'admin') {
            $user = $this->makeAdminUser();
        } elseif ($user !== null) {
            throw new Exception('Invalid user provided.');
        }

        self::assertDatabaseEmpty(AuthToken::class);

        $response = ($user === null ? $this : $this->actingAs($user))->graphQL('
            mutation ($input: CreateAuthenticationTokenInput!) {
                createAuthenticationToken(input: $input) {
                    token {
                        id
                    }
                    message
                }
            }
        ', [
            'input' => [
                'scope' => Str::upper($scope),
                'expiration' => Carbon::now()->addDay()->toIso8601String(),
            ],
        ]);

        if ($canCreateAuthToken) {
            $token = AuthToken::firstOrFail();
            $response->assertExactJson([
                'data' => [
                    'createAuthenticationToken' => [
                        'token' => [
                            'id' => (string) $token->id,
                        ],
                        'message' => null,
                    ],
                ],
            ]);
            self::assertDatabaseHas(AuthToken::class, [
                'projectid' => null,
                'userid' => $user?->id,
                'scope' => $scope,
            ]);
        } else {
            $response->assertGraphQLErrorMessage('This action is unauthorized.');
            self::assertDatabaseEmpty(AuthToken::class);
        }
    }

    /**
     * @return array<array<mixed>>
     */
    public static function disableFullAccessAndGlobalSubmitOnlyTokensCases(): array
    {
        return [
            ['cdash.allow_full_access_tokens', true, 'full_access', true, null],
            ['cdash.allow_full_access_tokens', false, 'full_access', false, 'input.scope'],
            ['cdash.allow_full_access_tokens', true, 'submit_only', true, null],
            ['cdash.allow_full_access_tokens', false, 'submit_only', true, null],
            ['cdash.allow_submit_only_tokens', true, 'full_access', true, null],
            ['cdash.allow_submit_only_tokens', false, 'full_access', true, null],
            ['cdash.allow_submit_only_tokens', true, 'submit_only', true, null],
            ['cdash.allow_submit_only_tokens', false, 'submit_only', false, 'input.projectId'],
        ];
    }

    #[DataProvider('disableFullAccessAndGlobalSubmitOnlyTokensCases')]
    public function testDisableFullAccessAndGlobalSubmitOnlyTokens(
        string $configKey,
        bool $configValue,
        string $scope,
        bool $canCreateAuthToken,
        ?string $validationErrorKey,
    ): void {
        Config::set($configKey, $configValue);

        $user = $this->makeAdminUser();
        $project = $this->makePublicProject();
        $project->users()->attach($user, ['role' => Project::PROJECT_USER]);

        self::assertDatabaseEmpty(AuthToken::class);

        $response = $this->actingAs($user)->graphQL('
            mutation ($input: CreateAuthenticationTokenInput!) {
                createAuthenticationToken(input: $input) {
                    token {
                        id
                    }
                    message
                }
            }
        ', [
            'input' => [
                'scope' => Str::upper($scope),
                'expiration' => Carbon::now()->addDay()->toIso8601String(),
            ],
        ]);

        if ($canCreateAuthToken) {
            $token = AuthToken::firstOrFail();
            $response->assertExactJson([
                'data' => [
                    'createAuthenticationToken' => [
                        'token' => [
                            'id' => (string) $token->id,
                        ],
                        'message' => null,
                    ],
                ],
            ]);
            self::assertDatabaseHas(AuthToken::class, [
                'projectid' => null,
                'userid' => $user->id,
                'scope' => $scope,
            ]);
        } else {
            $response->assertGraphQLValidationKeys([$validationErrorKey]);
            self::assertDatabaseEmpty(AuthToken::class);
        }

        // Wipe the auth token if it exists so we can create a new one
        AuthToken::query()->delete();
        self::assertDatabaseEmpty(AuthToken::class);

        // It's impossible to disable project-specific submit-only tokens.
        $response = $this->actingAs($user)->graphQL('
            mutation ($input: CreateAuthenticationTokenInput!) {
                createAuthenticationToken(input: $input) {
                    token {
                        id
                    }
                    message
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'scope' => 'SUBMIT_ONLY',
                'expiration' => Carbon::now()->addDay()->toIso8601String(),
            ],
        ]);
        $token = AuthToken::firstOrFail();
        $response->assertExactJson([
            'data' => [
                'createAuthenticationToken' => [
                    'token' => [
                        'id' => (string) $token->id,
                    ],
                    'message' => null,
                ],
            ],
        ]);
        self::assertDatabaseHas(AuthToken::class, [
            'projectid' => $project->id,
            'userid' => $user->id,
            'scope' => 'submit_only',
        ]);
    }

    /**
     * CDash doesn't currently support full API access on a per-project basis.
     */
    public function testRejectsFullAccessScopeWhenProjectProvided(): void
    {
        $user = $this->makeAdminUser();
        $project = $this->makePublicProject();
        $project->users()->attach($user, ['role' => Project::PROJECT_USER]);

        self::assertDatabaseEmpty(AuthToken::class);

        $this->actingAs($user)->graphQL('
            mutation ($input: CreateAuthenticationTokenInput!) {
                createAuthenticationToken(input: $input) {
                    token {
                        id
                    }
                    message
                }
            }
        ', [
            'input' => [
                'scope' => 'FULL_ACCESS',
                'expiration' => Carbon::now()->addDay()->toIso8601String(),
                'projectId' => $project->id,
            ],
        ])->assertGraphQLValidationKeys(['input.projectId']);
        self::assertDatabaseEmpty(AuthToken::class);
    }

    public function testCanSetDescription(): void
    {
        $user = $this->makeAdminUser();

        self::assertDatabaseEmpty(AuthToken::class);

        $description = Str::uuid()->toString();

        $response = $this->actingAs($user)->graphQL('
            mutation ($input: CreateAuthenticationTokenInput!) {
                createAuthenticationToken(input: $input) {
                    token {
                        description
                    }
                    message
                }
            }
        ', [
            'input' => [
                'scope' => 'FULL_ACCESS',
                'expiration' => Carbon::now()->addDay()->toIso8601String(),
                'description' => $description,
            ],
        ]);

        $response->assertExactJson([
            'data' => [
                'createAuthenticationToken' => [
                    'token' => [
                        'description' => $description,
                    ],
                    'message' => null,
                ],
            ],
        ]);
        self::assertDatabaseHas(AuthToken::class, [
            'userid' => $user->id,
            'scope' => 'full_access',
            'description' => $description,
        ]);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function expirationTimeCases(): array
    {
        $now = Carbon::now()->roundSecond();

        return [
            [$now->copy()->addDay(), (int) $now->diffInSeconds($now->copy()->addMonths(6)), true],
            [$now->copy()->subDay(), (int) $now->diffInSeconds($now->copy()->addMonths(6)), false],
            [$now->copy()->addYears(10), (int) $now->diffInSeconds($now->copy()->addMonths(6)), false],
            [$now->copy()->addYears(10), 0, true],
        ];
    }

    #[DataProvider('expirationTimeCases')]
    public function testExpirationTime(
        Carbon $expiration,
        int $tokenDurationConfig,
        bool $canCreateAuthToken,
    ): void {
        Config::set('cdash.token_duration', $tokenDurationConfig);

        $user = $this->makeAdminUser();

        self::assertDatabaseEmpty(AuthToken::class);

        $response = $this->actingAs($user)->graphQL('
            mutation ($input: CreateAuthenticationTokenInput!) {
                createAuthenticationToken(input: $input) {
                    token {
                        id
                    }
                    message
                }
            }
        ', [
            'input' => [
                'scope' => 'FULL_ACCESS',
                'expiration' => $expiration->toIso8601String(),
            ],
        ]);

        if ($canCreateAuthToken) {
            $token = AuthToken::firstOrFail();
            $response->assertExactJson([
                'data' => [
                    'createAuthenticationToken' => [
                        'token' => [
                            'id' => (string) $token->id,
                        ],
                        'message' => null,
                    ],
                ],
            ]);
            self::assertDatabaseHas(AuthToken::class, [
                'userid' => $user->id,
                'expires' => $expiration,
            ]);
        } else {
            $response->assertGraphQLValidationKeys(['input.expiration']);
            self::assertDatabaseEmpty(AuthToken::class);
        }
    }
}
