<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PruneAuthTokens;
use App\Mail\AuthTokenExpired;
use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class PruneAuthTokensTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

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

    public function testExpiredAuthTokenDeleted(): void
    {
        Mail::fake();

        $hash = Str::uuid()->toString();
        AuthToken::create([
            'hash' => $hash,
            'expires' => Carbon::now()->subMinute(),
            'scope' => 'test',
            'userid' => $this->user->id,
        ]);

        self::assertNotNull(AuthToken::find($hash));

        PruneAuthTokens::dispatch();

        self::assertNull(AuthToken::find($hash));

        Mail::assertQueuedCount(1);
        Mail::assertQueued(AuthTokenExpired::class);
    }

    public function testValidAuthTokenNotDeleted(): void
    {
        $hash = Str::uuid()->toString();
        AuthToken::create([
            'hash' => $hash,
            'expires' => Carbon::now()->addMinute(),
            'scope' => 'test',
            'userid' => $this->user->id,
        ]);

        self::assertNotNull(AuthToken::find($hash));

        PruneAuthTokens::dispatch();

        self::assertNotNull(AuthToken::find($hash));
    }
}
