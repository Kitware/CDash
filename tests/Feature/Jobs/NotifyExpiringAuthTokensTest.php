<?php

namespace Tests\Feature\Jobs;

use App\Enums\AuthTokenScope;
use App\Jobs\NotifyExpiringAuthTokens;
use App\Mail\AuthTokenExpiring;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotifyExpiringAuthTokensTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function tearDown(): void
    {
        $this->user->delete();

        parent::tearDown();
    }

    public function testValidAuthTokenNotNotified(): void
    {
        Mail::fake();

        $this->user->authenticationTokens()->create([
            'hash' => Str::uuid()->toString(),
            'expires' => Carbon::now()->addDays(8),
            'scope' => AuthTokenScope::FULL_ACCESS,
        ]);

        NotifyExpiringAuthTokens::dispatch();
        Mail::assertNothingQueued();
    }

    public function testAuthTokenExpiringInSixDaysNotified(): void
    {
        Mail::fake();

        $this->user->authenticationTokens()->create([
            'hash' => Str::uuid()->toString(),
            'expires' => Carbon::now()->addDays(6),
            'scope' => AuthTokenScope::FULL_ACCESS,
        ]);

        NotifyExpiringAuthTokens::dispatch();
        Mail::assertQueuedCount(1);
        Mail::assertQueued(AuthTokenExpiring::class);
    }

    public function testAuthTokenExpiringInLessThanOneDayNotified(): void
    {
        Mail::fake();

        $this->user->authenticationTokens()->create([
            'hash' => Str::uuid()->toString(),
            'expires' => Carbon::now()->addHour(),
            'scope' => AuthTokenScope::FULL_ACCESS,
        ]);

        NotifyExpiringAuthTokens::dispatch();
        Mail::assertQueuedCount(1);
        Mail::assertQueued(AuthTokenExpiring::class);
    }
}
