<?php

namespace Tests\Feature;

use App\Mail\ConfirmEmailChange;
use App\Mail\EmailChanged;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\LogsInThroughLoginForm;

class ProfileEmailChangeTest extends TestCase
{
    use DatabaseTransactions;
    use LogsInThroughLoginForm;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->user = User::factory()->create(['password' => 'Password1!']);
    }

    protected function tearDown(): void
    {
        $_POST = [];

        parent::tearDown();
    }

    /**
     * @return TestResponse<Response>
     */
    private function updateProfile(string $email, string $password = 'Password1!'): TestResponse
    {
        $_POST = [
            'fname' => 'New',
            'lname' => 'Name',
            'email' => $email,
            'password' => $password,
            'institution' => 'Kitware',
            'updateprofile' => 'Update Profile',
        ];
        $response = $this->actingAs($this->user)->post('/profile');
        $_POST = [];
        return $response;
    }

    private function confirmationUrl(string $email): string
    {
        $mails = Mail::queued(ConfirmEmailChange::class, fn (ConfirmEmailChange $mail): bool => $mail->hasTo($email));
        self::assertCount(1, $mails);
        $mail = $mails->first();
        self::assertInstanceOf(ConfirmEmailChange::class, $mail);
        return $mail->url;
    }

    private function newEmail(): string
    {
        return Str::uuid()->toString() . '@example.com';
    }

    /**
     * @return array<string, array{array<string, bool>, bool, bool, bool}>
     */
    public static function emailChangeCases(): array
    {
        return [
            'default' => [[], false, false, true],
            'password authentication disabled' => [['cdash.username_password_authentication_enabled' => false], false, false, true],
            'LDAP enabled, local account' => [['cdash.ldap_enabled' => true], false, false, true],
            'LDAP enabled, LDAP account' => [['cdash.ldap_enabled' => true], true, false, false],
            'LDAP disabled, former LDAP account' => [[], true, false, true],
            'logged in with OAuth or SAML' => [[], false, true, false],
        ];
    }

    /**
     * @param array<string, bool> $config
     */
    #[DataProvider('emailChangeCases')]
    public function testProfileEmailChange(array $config, bool $ldap_account, bool $password_login_disabled, bool $allowed): void
    {
        config($config);
        if ($ldap_account) {
            $this->user->ldapguid = Str::uuid()->toString();
        }
        $this->user->password_login_disabled = $password_login_disabled;
        $this->user->save();
        $old_email = $this->user->email;
        $new_email = $this->newEmail();

        $this->actingAs($this->user)
            ->get('/profile')
            ->assertOk()
            ->assertViewHas('props', fn (array $props): bool => $props['can-change-email'] === $allowed);

        $this->updateProfile($new_email)->assertSee('Your profile has been updated');

        $this->user->refresh();
        self::assertSame($old_email, $this->user->email);
        self::assertSame(['New', 'Name', 'Kitware'], [$this->user->firstname, $this->user->lastname, $this->user->institution]);

        if ($allowed) {
            Mail::assertQueuedCount(1);
            $this->actingAs($this->user)
                ->get($this->confirmationUrl($new_email))
                ->assertRedirect('/profile');
            self::assertSame($new_email, $this->user->refresh()->email);
        } else {
            Mail::assertNothingQueued();
        }
    }

    public function testConfirmingNewAddressVerifiesItAndNotifiesOldAddress(): void
    {
        $this->user = User::factory()->unverified()->create(['password' => 'Password1!']);
        $old_email = $this->user->email;
        $new_email = $this->newEmail();

        $this->updateProfile($new_email)->assertSee("To finish changing your email address, click the link sent to {$new_email}.");
        Mail::assertQueued(ConfirmEmailChange::class, fn (ConfirmEmailChange $mail): bool => $mail->hasTo($new_email) && $mail->email === $new_email);

        $this->actingAs($this->user)
            ->followingRedirects()
            ->get($this->confirmationUrl($new_email))
            ->assertSee('Your email address has been changed.');

        $this->user->refresh();
        self::assertSame($new_email, $this->user->email);
        self::assertTrue($this->user->hasVerifiedEmail());
        Mail::assertQueued(EmailChanged::class, fn (EmailChanged $mail): bool => $mail->hasTo($old_email) && $mail->oldEmail === $old_email && $mail->newEmail === $new_email);
        Mail::assertQueuedCount(2);
    }

    public function testGuestsLogInBeforeConfirming(): void
    {
        $new_email = $this->newEmail();
        $this->updateProfile($new_email);
        $url = $this->confirmationUrl($new_email);
        auth()->forgetGuards();

        $this->get($url)->assertRedirect('/login');
        $intended_url = $this->loginThroughForm($this->user, 'Password1!')->headers->get('Location');
        self::assertIsString($intended_url);
        $this->get($intended_url)->assertRedirect('/profile');

        self::assertSame($new_email, $this->user->refresh()->email);
    }

    public function testIncorrectPasswordIsRejected(): void
    {
        $email = $this->user->email;

        $this->updateProfile($this->newEmail(), 'wrong')->assertSee('Your password is incorrect.');

        $this->user->refresh();
        self::assertSame($email, $this->user->email);
        self::assertNotSame('New', $this->user->firstname);
        Mail::assertNothingQueued();
    }

    public function testMissingPasswordIsRejected(): void
    {
        $_POST = [
            'fname' => 'New',
            'lname' => 'Name',
            'email' => $this->newEmail(),
            'institution' => 'Kitware',
            'updateprofile' => 'Update Profile',
        ];
        $this->actingAs($this->user)->post('/profile')->assertSee('Your password is incorrect.');

        Mail::assertNothingQueued();
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->updateProfile('not an email')->assertSee('The email must be a valid email address.');

        Mail::assertNothingQueued();
    }

    public function testEmailInUseIsRejectedRegardlessOfCase(): void
    {
        $other_user = User::factory()->create();

        $this->updateProfile(Str::upper($other_user->email))->assertSee('The email has already been taken.');

        Mail::assertNothingQueued();
    }

    public function testChangingOtherFieldsDoesNotNeedPassword(): void
    {
        $email = $this->user->email;

        $this->updateProfile($email, '')->assertSee('Your profile has been updated.');

        self::assertSame('New', $this->user->refresh()->firstname);
        Mail::assertNothingQueued();
    }

    public function testEmailIsTrimmed(): void
    {
        $email = $this->user->email;

        $this->updateProfile(" {$email} ", '')->assertSee('Your profile has been updated.');

        self::assertSame($email, $this->user->refresh()->email);
        Mail::assertNothingQueued();
    }

    public function testOtherUsersCannotConfirm(): void
    {
        $email = $this->user->email;
        $new_email = $this->newEmail();
        $this->updateProfile($new_email);
        $this->flushSession();

        $this->actingAs(User::factory()->create())
            ->get($this->confirmationUrl($new_email))
            ->assertForbidden();

        self::assertSame($email, $this->user->refresh()->email);
    }

    public function testModifiedLinkIsRejected(): void
    {
        $email = $this->user->email;
        $new_email = $this->newEmail();
        $this->updateProfile($new_email);
        $url = str_replace(urlencode($new_email), urlencode($this->newEmail()), $this->confirmationUrl($new_email));

        $this->actingAs($this->user)->get($url)->assertForbidden();

        self::assertSame($email, $this->user->refresh()->email);
    }

    public function testExpiredLinkIsRejected(): void
    {
        $email = $this->user->email;
        $new_email = $this->newEmail();
        $this->updateProfile($new_email);

        $this->travel(61)->minutes();
        $this->actingAs($this->user)->get($this->confirmationUrl($new_email))->assertForbidden();

        self::assertSame($email, $this->user->refresh()->email);
    }

    public function testLinkStopsWorkingOnceAddressChanges(): void
    {
        $first_email = $this->newEmail();
        $second_email = $this->newEmail();
        $this->updateProfile($first_email);
        $this->updateProfile($second_email);

        $this->actingAs($this->user)->get($this->confirmationUrl($second_email))->assertRedirect('/profile');
        $this->actingAs($this->user)->get($this->confirmationUrl($first_email))->assertForbidden();
        $this->actingAs($this->user)->get($this->confirmationUrl($second_email))->assertForbidden();

        self::assertSame($second_email, $this->user->refresh()->email);
    }

    public function testAddressTakenBeforeConfirmationIsRejected(): void
    {
        $email = $this->user->email;
        $new_email = $this->newEmail();
        $this->updateProfile($new_email);
        User::factory()->create(['email' => Str::upper($new_email)]);

        $this->actingAs($this->user)
            ->get($this->confirmationUrl($new_email))
            ->assertConflict()
            ->assertSee('The email has already been taken.');

        self::assertSame($email, $this->user->refresh()->email);
    }

    public function testUsersWhoLogInExternallyAfterRequestingCannotConfirm(): void
    {
        $email = $this->user->email;
        $new_email = $this->newEmail();
        $this->updateProfile($new_email);
        $this->user->password_login_disabled = true;
        $this->user->save();

        $this->actingAs($this->user)->get($this->confirmationUrl($new_email))->assertForbidden();

        self::assertSame($email, $this->user->refresh()->email);
    }

    public function testDisabledEmailFieldIsNotSubmitted(): void
    {
        $this->user->password_login_disabled = true;
        $this->user->save();
        $email = $this->user->email;

        $_POST = [
            'fname' => 'New',
            'lname' => 'Name',
            'institution' => 'Kitware',
            'updateprofile' => 'Update Profile',
        ];
        $this->actingAs($this->user)->post('/profile')->assertSee('Your profile has been updated');

        self::assertSame($email, $this->user->refresh()->email);
        Mail::assertNothingQueued();
    }

    public function testUsersCannotChangeOtherUsersEmailAddresses(): void
    {
        $admin = User::factory()->adminUser()->create();

        $this->assertTrue($this->user->can('changeEmail', $this->user));
        $this->assertFalse($admin->can('changeEmail', $this->user));
    }
}
