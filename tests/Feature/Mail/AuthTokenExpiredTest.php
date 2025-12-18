<?php

namespace Tests\Feature\Mail;

use App\Mail\AuthTokenExpired;
use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class AuthTokenExpiredTest extends TestCase
{
    use CreatesUsers;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->makeNormalUser();
    }

    protected function tearDown(): void
    {
        $this->user->delete();

        parent::tearDown();
    }

    public function testMailableContainsLink(): void
    {
        /**
         * @var AuthToken $authtoken
         */
        $authtoken = $this->user->authTokens()->create([
            'hash' => Str::uuid()->toString(),
            'expires' => Carbon::now(),
            'scope' => 'test',
        ]);

        $mailable = new AuthTokenExpired($authtoken);

        $mailable->assertSeeInHtml(url('/user'));
    }

    public function testMailableNoDescription(): void
    {
        /**
         * @var AuthToken $authtoken
         */
        $authtoken = $this->user->authTokens()->create([
            'hash' => Str::uuid()->toString(),
            'expires' => Carbon::now(),
            'scope' => 'test',
        ]);

        $mailable = new AuthTokenExpired($authtoken);

        $mailable->assertSeeInHtml('* No Description');
    }

    public function testMailableShowsDescription(): void
    {
        /**
         * @var AuthToken $authtoken
         */
        $authtoken = $this->user->authTokens()->create([
            'hash' => Str::uuid()->toString(),
            'expires' => Carbon::now(),
            'scope' => 'test',
            'description' => Str::uuid()->toString(),
        ]);

        $mailable = new AuthTokenExpired($authtoken);

        $mailable->assertSeeInHtml('* ' . $authtoken->description);
    }
}
