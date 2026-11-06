<?php

namespace Tests\Feature;

use App\Listeners\Saml2Login;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use LdapRecord\Laravel\Events\Auth\Completed;
use LdapRecord\Models\OpenLDAP\User as LdapUser;
use Mockery;
use Psr\SimpleCache\InvalidArgumentException;
use Slides\Saml2\Events\SignedIn;
use Tests\TestCase;
use Tests\Traits\LogsInThroughLoginForm;

class ExternalLoginTest extends TestCase
{
    use DatabaseTransactions;
    use LogsInThroughLoginForm;

    private const PASSWORD = 'password';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'password' => Hash::make(self::PASSWORD),
        ]);
    }

    private function oauthLogin(string $email): void
    {
        Socialite::fake('pingidentity', SocialiteUser::fake([
            'id' => 1234567890,
            'email' => $email,
            'nickname' => 'Pseudo',
            'name' => 'First Last',
            'avatar' => 'https://en.gravatar.com/userimage',
        ]));

        $this->get('/auth/pingidentity/callback')->assertRedirect('/profile');
    }

    /**
     * @throws InvalidArgumentException
     */
    private function samlLogin(string $email): void
    {
        $base_auth = Mockery::mock('OneLogin\Saml2\Auth');
        $base_auth->shouldReceive('getLastAssertionNotOnOrAfter')->andReturn(5);
        $saml2_auth = Mockery::mock('Slides\Saml2\Auth');
        $saml2_auth->shouldReceive('getLastMessageId')->andReturn(Str::uuid()->toString());
        $saml2_auth->shouldReceive('getBase')->andReturn($base_auth);
        $saml2_user = Mockery::mock('Slides\Saml2\Saml2User');
        $saml2_user->shouldReceive('getUserId')->andReturn($email);

        (new Saml2Login())->handle(new SignedIn($saml2_user, $saml2_auth));
    }

    public function testOAuthLoginDisablesPasswordLogin(): void
    {
        $password = $this->user->password;
        $remember_token = $this->user->getRememberToken();

        $this->oauthLogin($this->user->email);

        $this->assertAuthenticatedAs($this->user);
        $this->user->refresh();
        self::assertTrue($this->user->password_login_disabled);
        self::assertNotSame($password, $this->user->password);
        self::assertNotSame($remember_token, $this->user->getRememberToken());
    }

    public function testOAuthRegistrationDisablesPasswordLogin(): void
    {
        $email = Str::uuid()->toString() . '@example.com';

        $this->oauthLogin($email);

        self::assertTrue(User::where('email', $email)->firstOrFail()->password_login_disabled);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testSamlLoginDisablesPasswordLogin(): void
    {
        $password = $this->user->password;

        $this->samlLogin($this->user->email);

        $this->assertAuthenticatedAs($this->user);
        $this->user->refresh();
        self::assertTrue($this->user->password_login_disabled);
        self::assertNotSame($password, $this->user->password);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testSamlRegistrationDisablesPasswordLogin(): void
    {
        config(['saml2.autoregister_new_users' => true]);
        $email = Str::uuid()->toString() . '@example.com';

        $this->samlLogin($email);

        self::assertTrue(User::where('email', $email)->firstOrFail()->password_login_disabled);
    }

    public function testLdapLoginDisablesPasswordLogin(): void
    {
        event(new Completed(new LdapUser(), $this->user));

        self::assertTrue($this->user->refresh()->password_login_disabled);
    }

    public function testPasswordIsOnlyReplacedOnFirstExternalLogin(): void
    {
        $this->user->recordExternalLogin();
        $password = $this->user->password;
        $remember_token = $this->user->getRememberToken();

        $this->user->recordExternalLogin();

        $this->user->refresh();
        self::assertSame($password, $this->user->password);
        self::assertSame($remember_token, $this->user->getRememberToken());
    }

    public function testFirstExternalLoginLogsOutOtherSessions(): void
    {
        $this->loginThroughForm($this->user, self::PASSWORD);
        $this->get('/profile')->assertOk();
        $other_session = session()->all();

        $this->startNewSession();
        $this->oauthLogin($this->user->email);
        $new_session = session()->all();

        $this->startNewSession();
        $this->withSession($other_session)->get('/profile')->assertRedirect('/login');
        $this->assertGuest();

        $this->startNewSession();
        $this->withSession($new_session)->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($this->user);
    }

    public function testExternalLoginDeletesPasswordResetTokens(): void
    {
        $token = Password::createToken($this->user);

        $this->user->recordExternalLogin();

        self::assertFalse(Password::tokenExists($this->user, $token));
    }

    public function testUsersWhoLoggedInExternallyCannotLogInWithPassword(): void
    {
        $this->user->password_login_disabled = true;
        $this->user->save();

        $this->loginThroughForm($this->user, self::PASSWORD)->assertUnauthorized();
        $this->assertGuest();
    }
}
