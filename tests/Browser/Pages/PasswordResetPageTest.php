<?php

namespace Tests\Browser\Pages;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;

class PasswordResetPageTest extends BrowserTestCase
{
    private ?User $user = null;

    public function tearDown(): void
    {
        if ($this->user !== null) {
            DB::table('password_resets')->where('email', $this->user->email)->delete();
            $this->user->delete();
            $this->user = null;
        }

        parent::tearDown();
    }

    private static function message(string $key): string
    {
        $message = trans($key);
        self::assertIsString($message);
        return $message;
    }

    public function testCanRequestResetLink(): void
    {
        $this->browse(function (Browser $browser): void {
            // Use an address without an account so that no email is sent.
            $browser->visit('/login')
                ->clickLink('Forgot Password?')
                ->waitForLocation('/password/reset')
                ->type('@email-input', Str::uuid()->toString() . '@example.com')
                ->click('@send-link-button')
                ->waitFor('@status-message')
                ->assertPathIs('/password/reset')
                ->assertSeeIn('@status-message', self::message('passwords.sent'));
        });
    }

    public function testResetLogsOutOtherBrowsers(): void
    {
        $old_password = Str::random(16);
        $new_password = Str::random(16);
        $user = User::factory()->create(['password' => Hash::make($old_password)]);
        $this->user = $user;
        $token = Password::createToken($user);

        $this->browse(function (Browser $first, Browser $second) use ($user, $old_password, $new_password, $token): void {
            $first->visit('/login')
                ->type('email', $user->email)
                ->type('password', $old_password)
                ->waitForReload(fn (Browser $browser) => $browser->press('Sign In'))
                ->visit('/profile')
                ->assertPathIs('/profile');

            $second->visit("/password/reset/$token?email=" . urlencode($user->email))
                ->type('@password-input', $new_password)
                ->type('@password-confirmation-input', $new_password)
                ->click('@reset-password-button')
                ->waitFor('@status-message')
                ->assertPathIs('/login')
                ->assertSeeIn('@status-message', self::message('passwords.reset'));

            $first->visit('/profile')
                ->assertPathIs('/login');

            $second->type('email', $user->email)
                ->type('password', $new_password)
                ->waitForReload(fn (Browser $browser) => $browser->press('Sign In'))
                ->visit('/profile')
                ->assertPathIs('/profile');
        });
    }
}
