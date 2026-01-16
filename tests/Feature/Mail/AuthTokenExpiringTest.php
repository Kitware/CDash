<?php

namespace Tests\Feature\Mail;

use App\Mail\AuthTokenExpiring;
use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class AuthTokenExpiringTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

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

    public function testMailableContainsExpirationDate(): void
    {
        /**
         * @var AuthToken $authtoken
         */
        $authtoken = $this->user->authTokens()->create([
            'hash' => Str::uuid()->toString(),
            'expires' => Carbon::create(2025, 3, 14),
            'scope' => 'test',
        ]);

        $mailable = new AuthTokenExpiring($authtoken);

        $mailable->assertSeeInHtml('2025-03-14');
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

        $mailable = new AuthTokenExpiring($authtoken);

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

        $mailable = new AuthTokenExpiring($authtoken);

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

        $mailable = new AuthTokenExpiring($authtoken);

        $mailable->assertSeeInHtml('* ' . $authtoken->description);
    }
}
