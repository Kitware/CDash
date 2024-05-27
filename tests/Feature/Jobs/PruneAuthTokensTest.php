<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PruneAuthTokens;
use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class PruneAuthTokensTest extends TestCase
{
    use CreatesUsers;

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
        $hash = Str::uuid()->toString();
        AuthToken::create([
            'hash' => $hash,
            'expires' => Carbon::now()->subMinute(),
            'scope' => 'test',
            'userid' => $this->user->id,
        ]);

        self::assertNotNull(Authtoken::find($hash));

        PruneAuthTokens::dispatch();

        self::assertNull(Authtoken::find($hash));
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

        self::assertNotNull(Authtoken::find($hash));

        PruneAuthTokens::dispatch();

        self::assertNotNull(Authtoken::find($hash));
    }
}
