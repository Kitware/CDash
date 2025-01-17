<?php

namespace Tests\Feature\Jobs;

use App\Jobs\NotifyExpiringAuthTokens;
use App\Mail\AuthTokenExpiring;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class NotifyExpiringAuthTokensTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTruncation;

    protected User $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = $this->makeNormalUser();
    }

    public function tearDown(): void
    {
        $this->user->delete();

        parent::tearDown();
    }

    public function testValidAuthTokenNotNotified(): void
    {
        Mail::fake();

        $this->user->authTokens()->create([
            'hash' => Str::uuid()->toString(),
            'expires' => Carbon::now()->addDays(8),
            'scope' => 'test',
        ]);

        NotifyExpiringAuthTokens::dispatch();
        Mail::assertNothingQueued();
    }

    public function testAuthTokenExpiringInSixDaysNotified(): void
    {
        Mail::fake();

        $this->user->authTokens()->create([
            'hash' => Str::uuid()->toString(),
            'expires' => Carbon::now()->addDays(6),
            'scope' => 'test',
        ]);

        NotifyExpiringAuthTokens::dispatch();
        Mail::assertQueuedCount(1);
        Mail::assertQueued(AuthTokenExpiring::class);
    }

    public function testAuthTokenExpiringInLessThanOneDayNotified(): void
    {
        Mail::fake();

        $this->user->authTokens()->create([
            'hash' => Str::uuid()->toString(),
            'expires' => Carbon::now()->addHour(),
            'scope' => 'test',
        ]);

        NotifyExpiringAuthTokens::dispatch();
        Mail::assertQueuedCount(1);
        Mail::assertQueued(AuthTokenExpiring::class);
    }
}
