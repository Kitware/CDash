<?php

namespace Tests\Feature\Services;

use App\Enums\AuthTokenScope;
use App\Enums\ProjectRole;
use App\Models\AuthToken;
use App\Models\Project;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class AuthTokenServiceTest extends TestCase
{
    use CreatesProjects;

    use DatabaseTransactions;

    private function createToken(
        User $user,
        AuthTokenScope $scope = AuthTokenScope::FULL_ACCESS,
        ?Project $project = null,
        ?Carbon $expires = null,
    ): AuthToken {
        /** @var AuthToken $token */
        $token = $user->authenticationTokens()->save(AuthToken::factory()->make([
            'scope' => $scope,
            'projectid' => $project?->id,
            'expires' => $expires ?? Carbon::now()->addDay(),
        ]));
        return $token;
    }

    public function testGenerateCreatesFullAccessToken(): void
    {
        $user = User::factory()->create();

        [
            'raw_token' => $raw_token,
            'token' => $token,
        ] = AuthTokenService::generate($user->id, -1, AuthTokenScope::FULL_ACCESS, 'test description');

        self::assertSame(86, strlen($raw_token));
        self::assertSame(hash('sha512', $raw_token), $token->hash);
        self::assertDatabaseHas(AuthToken::class, [
            'id' => $token->id,
            'hash' => hash('sha512', $raw_token),
            'userid' => $user->id,
            'scope' => AuthTokenScope::FULL_ACCESS,
            'description' => 'test description',
            'projectid' => null,
        ]);
    }

    public function testGenerateUsesConfiguredDurationWhenNoExpirationProvided(): void
    {
        Config::set('cdash.token_duration', 3600);
        $user = User::factory()->create();

        $before = time();
        ['token' => $token] = AuthTokenService::generate($user->id, -1, AuthTokenScope::FULL_ACCESS, '');
        $after = time();

        $expires = $token->refresh()->expires->getTimestamp();
        self::assertGreaterThanOrEqual($before + 3600, $expires);
        self::assertLessThanOrEqual($after + 3600, $expires);
    }

    public function testGenerateTokenNeverExpiresWhenDurationIsZero(): void
    {
        Config::set('cdash.token_duration', 0);
        $user = User::factory()->create();

        ['token' => $token] = AuthTokenService::generate($user->id, -1, AuthTokenScope::FULL_ACCESS, '');

        self::assertSame(9999, $token->refresh()->expires->year);
    }

    public function testGenerateUsesProvidedExpiration(): void
    {
        $user = User::factory()->create();
        $expiration = Carbon::now()->addDays(3)->startOfSecond();

        ['token' => $token] = AuthTokenService::generate($user->id, -1, AuthTokenScope::FULL_ACCESS, '', $expiration);

        self::assertTrue($expiration->eq($token->refresh()->expires));
    }

    /**
     * @return array<array{mixed}>
     */
    public static function invalidDurationCases(): array
    {
        return [
            [-1],
            ['abc'],
        ];
    }

    #[DataProvider('invalidDurationCases')]
    public function testGenerateRejectsInvalidDurationConfiguration(mixed $duration): void
    {
        Config::set('cdash.token_duration', $duration);
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token_duration configuration');
        AuthTokenService::generate($user->id, -1, AuthTokenScope::FULL_ACCESS, '');
    }

    public function testGenerateRejectsFullAccessTokenWhenDisabledByConfig(): void
    {
        Config::set('cdash.allow_full_access_tokens', false);
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Full-access tokens are prohibited by config');
        AuthTokenService::generate($user->id, -1, AuthTokenScope::FULL_ACCESS, '');
    }

    public function testGenerateRejectsGlobalSubmitOnlyTokenWhenDisabledByConfig(): void
    {
        Config::set('cdash.allow_submit_only_tokens', false);
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only project-specific submit-only tokens allowed by config');
        AuthTokenService::generate($user->id, -1, AuthTokenScope::SUBMIT_ONLY, '');
    }

    public function testGenerateCreatesGlobalSubmitOnlyToken(): void
    {
        $user = User::factory()->create();

        ['token' => $token] = AuthTokenService::generate($user->id, -1, AuthTokenScope::SUBMIT_ONLY, '');

        self::assertDatabaseHas(AuthToken::class, [
            'id' => $token->id,
            'scope' => AuthTokenScope::SUBMIT_ONLY,
            'projectid' => null,
        ]);
    }

    /**
     * @return array<array{bool}>
     */
    public static function allowSubmitOnlyTokensCases(): array
    {
        return [
            [true],
            [false],
        ];
    }

    #[DataProvider('allowSubmitOnlyTokensCases')]
    public function testGenerateCreatesProjectScopedSubmitOnlyToken(bool $allowGlobalSubmitOnlyTokens): void
    {
        Config::set('cdash.allow_submit_only_tokens', $allowGlobalSubmitOnlyTokens);
        $user = User::factory()->create();
        $project = $this->makePublicProject();

        ['token' => $token] = AuthTokenService::generate($user->id, $project->id, AuthTokenScope::SUBMIT_ONLY, '');

        self::assertDatabaseHas(AuthToken::class, [
            'id' => $token->id,
            'scope' => AuthTokenScope::SUBMIT_ONLY,
            'projectid' => $project->id,
        ]);
    }

    public function testGenerateFullAccessTokenIsNeverProjectScoped(): void
    {
        $user = User::factory()->create();
        $project = $this->makePublicProject();

        ['token' => $token] = AuthTokenService::generate($user->id, $project->id, AuthTokenScope::FULL_ACCESS, '');

        self::assertDatabaseHas(AuthToken::class, [
            'id' => $token->id,
            'scope' => AuthTokenScope::FULL_ACCESS,
            'projectid' => null,
        ]);
    }

    public function testGenerateRejectsNonexistentProject(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid project');
        AuthTokenService::generate($user->id, 123456789, AuthTokenScope::SUBMIT_ONLY, '');
    }

    public function testCheckAcceptsValidFullAccessToken(): void
    {
        $token = $this->createToken(User::factory()->create());

        self::assertTrue(AuthTokenService::check($token->hash, $this->makePublicProject()->id));
    }

    public function testCheckRejectsUnknownToken(): void
    {
        $project = $this->makePublicProject();

        self::assertFalse(AuthTokenService::check(AuthTokenService::hash(Str::uuid()->toString()), $project->id));
    }

    public function testCheckRejectsExpiredTokenWithoutDeletingIt(): void
    {
        $token = $this->createToken(User::factory()->create(), expires: Carbon::now()->subMinute());

        self::assertFalse(AuthTokenService::check($token->hash, $this->makePublicProject()->id));
        self::assertModelExists($token);
    }

    public function testCheckRejectsNonexistentProject(): void
    {
        $token = $this->createToken(User::factory()->create());

        self::assertFalse(AuthTokenService::check($token->hash, 123456789));
    }

    /**
     * @return array<array{?ProjectRole, bool}>
     */
    public static function privateProjectCases(): array
    {
        return [
            [null, false],
            [ProjectRole::USER, true],
            [ProjectRole::ADMINISTRATOR, true],
        ];
    }

    #[DataProvider('privateProjectCases')]
    public function testCheckPrivateProjectRequiresTokenOwnerMembership(?ProjectRole $role, bool $valid): void
    {
        $user = User::factory()->create();
        $project = $this->makePrivateProject();
        if ($role !== null) {
            $project->users()->attach($user, ['role' => $role]);
        }
        $token = $this->createToken($user);

        // The token owner's permissions matter, not those of the currently logged-in user.
        $this->actingAs(User::factory()->adminUser()->create());
        self::assertSame($valid, AuthTokenService::check($token->hash, $project->id));
    }

    public function testCheckProjectScopedSubmitOnlyTokenOnlyValidForItsProject(): void
    {
        $project = $this->makePublicProject();
        $token = $this->createToken(User::factory()->create(), AuthTokenScope::SUBMIT_ONLY, $project);

        self::assertTrue(AuthTokenService::check($token->hash, $project->id));
        self::assertFalse(AuthTokenService::check($token->hash, $this->makePublicProject()->id));
    }

    public function testCheckProjectScopedSubmitOnlyTokenValidWhenGlobalSubmitOnlyTokensDisabled(): void
    {
        Config::set('cdash.allow_submit_only_tokens', false);
        $project = $this->makePublicProject();
        $token = $this->createToken(User::factory()->create(), AuthTokenScope::SUBMIT_ONLY, $project);

        self::assertTrue(AuthTokenService::check($token->hash, $project->id));
    }

    #[DataProvider('allowSubmitOnlyTokensCases')]
    public function testCheckGlobalSubmitOnlyTokenRespectsConfig(bool $allowGlobalSubmitOnlyTokens): void
    {
        Config::set('cdash.allow_submit_only_tokens', $allowGlobalSubmitOnlyTokens);
        $token = $this->createToken(User::factory()->create(), AuthTokenScope::SUBMIT_ONLY);

        self::assertSame($allowGlobalSubmitOnlyTokens, AuthTokenService::check($token->hash, $this->makePublicProject()->id));
    }

    public function testCheckRejectsFullAccessTokenWhenDisabledByConfig(): void
    {
        Config::set('cdash.allow_full_access_tokens', false);
        $token = $this->createToken(User::factory()->create());

        self::assertFalse(AuthTokenService::check($token->hash, $this->makePublicProject()->id));
    }

    public function testHash(): void
    {
        self::assertSame('', AuthTokenService::hash(null));
        self::assertSame('', AuthTokenService::hash(''));
        self::assertSame(hash('sha512', 'abc'), AuthTokenService::hash('abc'));
    }
}
