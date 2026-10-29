<?php

namespace Tests\Feature;

use App\Enums\AuthTokenScope;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\LogsInThroughLoginForm;

class PasswordResetTest extends TestCase
{
    use DatabaseTransactions;
    use LogsInThroughLoginForm;

    private const OLD_PASSWORD = 'old-password';
    private const NEW_PASSWORD = 'new-password';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->user = User::factory()->create([
            'password' => Hash::make(self::OLD_PASSWORD),
            'password_updated_at' => Carbon::now()->subYear(),
        ]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function requestResetLink(string $email): TestResponse
    {
        return $this->from('/password/reset')->post('/password/email', ['email' => $email]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function resetPassword(string $token, string $password = self::NEW_PASSWORD, ?string $email = null): TestResponse
    {
        return $this->from("/password/reset/$token")->post('/password/reset', [
            'token' => $token,
            'email' => $email ?? $this->user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    private static function message(string $key): string
    {
        $message = trans($key);
        self::assertIsString($message);
        return $message;
    }

    /**
     * Returns the token from the only password reset email sent to the user.
     */
    private function sentToken(): string
    {
        $notifications = Notification::sent($this->user, ResetPassword::class);
        self::assertCount(1, $notifications);
        $notification = $notifications->first();
        self::assertInstanceOf(ResetPassword::class, $notification);
        return $notification->token;
    }

    private function assertPasswordIs(string $password): void
    {
        self::assertTrue(Hash::check($password, $this->user->refresh()->password));
    }

    public function testLoginPageLinksToResetForm(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('password.request'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSeeText('Send Password Reset Link');
    }

    public function testLegacyUrlRedirectsToResetForm(): void
    {
        $this->get('/recoverPassword.php')
            ->assertStatus(301)
            ->assertRedirect('/password/reset');
    }

    public function testRequestingResetLinkEmailsUser(): void
    {
        $this->requestResetLink($this->user->email)
            ->assertRedirect('/password/reset')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', self::message('passwords.sent'));

        Notification::assertSentToTimes($this->user, ResetPassword::class, 1);
        Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification): bool {
            $url = url('/password/reset/' . $notification->token . '?email=' . urlencode($this->user->email));
            self::assertSame($url, $notification->toMail($this->user)->actionUrl);
            self::assertTrue(Password::tokenExists($this->user, $notification->token));
            return true;
        });

        $this->get('/password/reset')->assertSeeText(self::message('passwords.sent'));
    }

    public function testRequestingResetLinkForUnknownEmailLooksTheSame(): void
    {
        $this->requestResetLink(Str::uuid()->toString() . '@example.com')
            ->assertRedirect('/password/reset')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', self::message('passwords.sent'));

        $this->postJson('/password/email', ['email' => Str::uuid()->toString() . '@example.com'])
            ->assertOk()
            ->assertExactJson(['message' => self::message('passwords.sent')]);

        Notification::assertNothingSent();
    }

    public function testRequestingResetLinkRequiresValidEmail(): void
    {
        $this->requestResetLink('not an email address')
            ->assertRedirect('/password/reset')
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function testResetLinksAreThrottledPerAccount(): void
    {
        $this->requestResetLink($this->user->email);
        $first_token = $this->sentToken();

        // A second request within a minute looks the same, but doesn't send another email.
        $this->requestResetLink($this->user->email)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', self::message('passwords.sent'));
        Notification::assertSentToTimes($this->user, ResetPassword::class, 1);

        // After a minute, a new link can be sent, which replaces the previous one.
        $this->travel(61)->seconds();
        $this->requestResetLink($this->user->email);
        Notification::assertSentToTimes($this->user, ResetPassword::class, 2);
        self::assertFalse(Password::tokenExists($this->user, $first_token));
    }

    public function testResetLinkRequestsAreRateLimited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->requestResetLink(Str::uuid()->toString() . '@example.com')->assertRedirect('/password/reset');
        }

        $this->requestResetLink($this->user->email)->assertTooManyRequests();
        Notification::assertNothingSent();
    }

    public function testCanViewResetForm(): void
    {
        $token = Password::createToken($this->user);

        $this->get("/password/reset/$token?email=" . urlencode($this->user->email))
            ->assertOk()
            ->assertSee($token)
            ->assertSee($this->user->email)
            ->assertSeeText('Confirm New Password');
    }

    public function testResetChangesPassword(): void
    {
        $this->freezeSecond();
        $old_remember_token = $this->user->getRememberToken();
        $token = Password::createToken($this->user);

        $this->resetPassword($token)
            ->assertSessionHasNoErrors()
            ->assertRedirect('/login')
            ->assertSessionHas('status', self::message('passwords.reset'));

        // The user must log in with their new password.
        $this->assertGuest();
        $this->get('/login')->assertSeeText(self::message('passwords.reset'));

        $this->assertPasswordIs(self::NEW_PASSWORD);
        self::assertTrue(Carbon::now()->equalTo($this->user->password_updated_at));
        self::assertNotSame($old_remember_token, $this->user->getRememberToken());
        self::assertFalse(Password::tokenExists($this->user, $token));

        $this->loginThroughForm($this->user, self::OLD_PASSWORD);
        $this->assertGuest();
        $this->loginThroughForm($this->user, self::NEW_PASSWORD);
        $this->assertAuthenticatedAs($this->user);
    }

    public function testResetTokenCanOnlyBeUsedOnce(): void
    {
        $token = Password::createToken($this->user);
        $this->resetPassword($token)->assertSessionHasNoErrors();

        $this->resetPassword($token, 'another-password')
            ->assertRedirect("/password/reset/$token")
            ->assertSessionHasErrors(['email' => self::message('passwords.token')]);
        $this->assertPasswordIs(self::NEW_PASSWORD);
    }

    public function testResetRejectsInvalidToken(): void
    {
        Password::createToken($this->user);

        $this->resetPassword(Str::random(64))
            ->assertSessionHasErrors(['email' => self::message('passwords.token')]);
        $this->assertPasswordIs(self::OLD_PASSWORD);
    }

    public function testResetRejectsTokenForAnotherAccount(): void
    {
        $other_user = User::factory()->create();
        $token = Password::createToken($other_user);

        $this->resetPassword($token)
            ->assertSessionHasErrors(['email' => self::message('passwords.token')]);
        $this->assertPasswordIs(self::OLD_PASSWORD);
    }

    public function testResetRejectsExpiredToken(): void
    {
        $token = Password::createToken($this->user);
        $this->travel(61)->minutes();

        $this->resetPassword($token)
            ->assertSessionHasErrors(['email' => self::message('passwords.token')]);
        $this->assertPasswordIs(self::OLD_PASSWORD);
    }

    public function testResetForUnknownEmailLooksLikeInvalidToken(): void
    {
        $token = Password::createToken($this->user);

        $this->resetPassword($token, email: Str::uuid()->toString() . '@example.com')
            ->assertSessionHasErrors(['email' => self::message('passwords.token')]);
        $this->assertPasswordIs(self::OLD_PASSWORD);
    }

    /**
     * @return array<string, array{array<string, int>, string, bool}>
     */
    public static function passwordPolicyCases(): array
    {
        return [
            'shorter than minimum length' => [['cdash.password.min' => 12], 'abcdefghijk', false],
            'minimum length' => [['cdash.password.min' => 12], 'abcdefghijkl', true],
            'minimum length below Laravel default' => [['cdash.password.min' => 3], 'abc', true],
            'not complex enough' => [['cdash.password.complexity' => 3, 'cdash.password.count' => 1], 'abcdefgh1', false],
            'complex enough' => [['cdash.password.complexity' => 3, 'cdash.password.count' => 1], 'Abcdefgh1', true],
            'not enough of each character type' => [['cdash.password.complexity' => 2, 'cdash.password.count' => 2], 'Abcdefgh1', false],
            'enough of each character type' => [['cdash.password.complexity' => 2, 'cdash.password.count' => 2], 'ABcdefgh12', true],
        ];
    }

    /**
     * @param array<string, int> $config
     */
    #[DataProvider('passwordPolicyCases')]
    public function testResetEnforcesPasswordPolicy(array $config, string $password, bool $allowed): void
    {
        config($config);
        $token = Password::createToken($this->user);

        $response = $this->resetPassword($token, $password);

        if ($allowed) {
            $response->assertSessionHasNoErrors();
            $this->assertPasswordIs($password);
        } else {
            $response->assertSessionHasErrors('password');
            $this->assertPasswordIs(self::OLD_PASSWORD);
        }
    }

    public function testResetRequiresConfirmation(): void
    {
        $token = Password::createToken($this->user);

        $this->post('/password/reset', [
            'token' => $token,
            'email' => $this->user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors('password');
        $this->assertPasswordIs(self::OLD_PASSWORD);
    }

    public function testResetRejectsCurrentPassword(): void
    {
        $token = Password::createToken($this->user);

        $this->resetPassword($token, self::OLD_PASSWORD)
            ->assertSessionHasErrors(['password' => 'New password matches old password.']);

        // The link can still be used to choose a different password.
        $this->resetPassword($token)->assertSessionHasNoErrors();
        $this->assertPasswordIs(self::NEW_PASSWORD);
    }

    public function testResetRenewsExpiredPassword(): void
    {
        config(['cdash.password.expires' => 30]);

        $this->loginThroughForm($this->user, self::OLD_PASSWORD);
        $this->get('/projects')->assertRedirect('/profile?password_expired=1');

        $this->startNewSession();
        $this->resetPassword(Password::createToken($this->user));
        $this->loginThroughForm($this->user, self::NEW_PASSWORD);
        $this->get('/projects')->assertOk();
    }

    public function testResetLogsOutOtherSessions(): void
    {
        // Log in on another device and use CDash.
        $this->loginThroughForm($this->user, self::OLD_PASSWORD);
        $this->get('/profile')->assertOk();
        $other_session = session()->all();

        $this->startNewSession();
        $this->resetPassword(Password::createToken($this->user))->assertSessionHasNoErrors();
        $this->loginThroughForm($this->user, self::NEW_PASSWORD);
        $this->assertAuthenticatedAs($this->user);
        $new_session = session()->all();

        // The other device is logged out of every part of CDash.
        $this->startNewSession();
        $this->withSession($other_session)->get('/profile')->assertRedirect('/login');
        $this->assertGuest();

        $this->startNewSession();
        $this->withSession($other_session)->getJson('/api/v1/manageSubProject.php')->assertUnauthorized();
        $this->assertGuest();

        $this->startNewSession();
        $this->withSession($other_session)->graphQL('query { me { id } }')->assertUnauthorized();
        $this->assertGuest();

        // The session in which the user logged in with their new password still works.
        $this->startNewSession();
        $this->withSession($new_session)->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($this->user);

        $this->startNewSession();
        $this->withSession($new_session)->graphQL('query { me { id } }')->assertExactJson([
            'data' => ['me' => ['id' => (string) $this->user->id]],
        ]);
    }

    public function testLoggingInAsAnotherUserReplacesSessionPasswordHash(): void
    {
        $this->loginThroughForm($this->user, self::OLD_PASSWORD);
        $this->get('/profile')->assertOk();

        // Use another user's API token with the same session.
        $other_user = User::factory()->adminUser()->create();
        ['raw_token' => $raw_token] = AuthTokenService::generate($other_user->id, -1, AuthTokenScope::FULL_ACCESS, '');
        $this->app['auth']->forgetGuards();
        $this->withToken($raw_token)->getJson('/api/monitor')->assertOk();
        $this->assertAuthenticatedAs($other_user);
    }

    /**
     * @return array<string, array{array<string, bool>, bool}>
     */
    public static function passwordResetDisabledCases(): array
    {
        return [
            'password authentication disabled' => [['cdash.username_password_authentication_enabled' => false], false],
            'LDAP enabled, local account' => [['cdash.ldap_enabled' => true], false],
            'LDAP enabled, LDAP account' => [['cdash.ldap_enabled' => true], true],
        ];
    }

    /**
     * @param array<string, bool> $config
     */
    #[DataProvider('passwordResetDisabledCases')]
    public function testPasswordResetDisabled(array $config, bool $ldap_account): void
    {
        config($config);
        if ($ldap_account) {
            $this->user->ldapguid = Str::uuid()->toString();
            $this->user->save();
        }
        $token = Password::createToken($this->user);

        $this->get('/login')->assertDontSee(route('password.request'));
        $this->get('/password/reset')->assertNotFound();
        $this->requestResetLink($this->user->email)->assertNotFound();
        $this->get("/password/reset/$token?email=" . urlencode($this->user->email))->assertNotFound();
        $this->resetPassword($token)->assertNotFound();

        Notification::assertNothingSent();
        $this->assertPasswordIs(self::OLD_PASSWORD);
    }

    public function testFormerLdapUsersCanResetPasswordWhenLdapDisabled(): void
    {
        $this->user->ldapguid = Str::uuid()->toString();
        $this->user->save();

        $this->requestResetLink($this->user->email);
        $token = $this->sentToken();

        $this->resetPassword($token)->assertSessionHasNoErrors();
        $this->assertPasswordIs(self::NEW_PASSWORD);
    }
}
