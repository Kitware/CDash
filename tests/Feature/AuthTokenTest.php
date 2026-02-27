<?php

namespace Tests\Feature;

use App\Models\AuthToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class AuthTokenTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

    /**
     * @return array<array<mixed>>
     */
    public static function setTokenExpirationCases(): array
    {
        $oneYear = Carbon::now()->addYear();
        $oneMonth = Carbon::now()->addMonth();
        $oneMonthAgo = Carbon::now()->subMonth();

        return [
            // Defaults to 1 year if no expiration provided
            [null, $oneYear, 0, true],
            // Basic case with no limit
            [$oneMonth, $oneMonth, 0, true],
            // Sets to limit if expiration beyond limit
            [$oneYear, $oneMonth, (int) Carbon::now()->diffInUTCSeconds($oneMonth), true],
            // Test expiration in the past
            [$oneMonthAgo, Carbon::now(), 0, false],
        ];
    }

    #[DataProvider('setTokenExpirationCases')]
    public function testSetTokenExpiration(
        ?Carbon $expirationParam,
        Carbon $expectedExpiration,
        int $tokenDuration,
        bool $shouldPass,
    ): void {
        config(['cdash.token_duration' => $tokenDuration]);
        $admin = $this->makeAdminUser();

        $response = $this->actingAs($admin)->post('/api/authtokens/create', [
            'description' => Str::uuid()->toString(),
            'scope' => AuthToken::SCOPE_FULL_ACCESS,
            'expiration' => $expirationParam,
        ]);

        if ($shouldPass) {
            $response->assertOk();
            $actualExpiration = Carbon::parse($response->json('token.expires'));
            self::assertEqualsWithDelta($expectedExpiration->timestamp, $actualExpiration->timestamp, 10);
        } else {
            $response->assertBadRequest();
        }
    }
}
