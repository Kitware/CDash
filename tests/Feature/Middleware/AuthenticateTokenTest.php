<?php

namespace Tests\Feature\Middleware;

use App\Enums\AuthTokenScope;
use App\Http\Middleware\AuthenticateToken;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticateTokenTest extends TestCase
{
    use DatabaseTransactions;

    private function handle(?string $raw_token): void
    {
        $server = $raw_token === null ? [] : ['HTTP_AUTHORIZATION' => "Bearer {$raw_token}"];
        $request = new Request(server: $server);

        $next_request = null;
        (new AuthenticateToken())->handle($request, function (Request $request) use (&$next_request) {
            $next_request = $request;
            return response('');
        });

        // The request should always continue down the middleware stack, whether or not we logged in.
        self::assertSame($request, $next_request);
    }

    public function testDoesNotAuthenticateWithoutBearerToken(): void
    {
        $this->handle(null);

        $this->assertGuest();
    }

    public function testAuthenticatesWithFullAccessToken(): void
    {
        $user = User::factory()->create();
        ['raw_token' => $raw_token] = AuthTokenService::generate($user->id, -1, AuthTokenScope::FULL_ACCESS, '');

        $this->handle($raw_token);

        $this->assertAuthenticatedAs($user);
    }

    public function testDoesNotAuthenticateWithUnknownToken(): void
    {
        $this->handle(Str::uuid()->toString());

        $this->assertGuest();
    }

    public function testDoesNotAuthenticateWithSubmitOnlyToken(): void
    {
        $user = User::factory()->create();
        [
            'raw_token' => $raw_token,
            'token' => $token,
        ] = AuthTokenService::generate($user->id, -1, AuthTokenScope::SUBMIT_ONLY, '');

        $this->handle($raw_token);

        $this->assertGuest();
        self::assertModelExists($token);
    }

    public function testDoesNotAuthenticateWithExpiredTokenOrDeleteIt(): void
    {
        $user = User::factory()->create();
        [
            'raw_token' => $raw_token,
            'token' => $token,
        ] = AuthTokenService::generate($user->id, -1, AuthTokenScope::FULL_ACCESS, '', Carbon::now()->subMinute());

        $this->handle($raw_token);

        $this->assertGuest();
        self::assertModelExists($token);
    }
}
