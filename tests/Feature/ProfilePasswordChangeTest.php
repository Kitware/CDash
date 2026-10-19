<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfilePasswordChangeTest extends TestCase
{
    use DatabaseTransactions;

    private const OLD_PASSWORD = 'old-password';
    private const NEW_PASSWORD = 'new-password';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'password' => Hash::make(self::OLD_PASSWORD),
            'password_updated_at' => Carbon::now()->subYear(),
        ]);
    }

    protected function tearDown(): void
    {
        // The profile page reads $_POST directly, so don't leak it into other tests.
        $_POST = [];

        parent::tearDown();
    }

    /**
     * @return array<string, array{array<string, bool>, bool, bool}>
     */
    public static function passwordChangeCases(): array
    {
        return [
            'default' => [[], false, true],
            'password authentication disabled' => [['cdash.username_password_authentication_enabled' => false], false, false],
            'LDAP enabled, local account' => [['cdash.ldap_enabled' => true], false, true],
            'LDAP enabled, LDAP account' => [['cdash.ldap_enabled' => true], true, false],
            'LDAP disabled, former LDAP account' => [[], true, true],
        ];
    }

    /**
     * @param array<string, bool> $config
     */
    private function setUpCase(array $config, bool $ldap_account): void
    {
        config($config);
        if ($ldap_account) {
            $this->user->ldapguid = Str::uuid()->toString();
            $this->user->save();
        }
    }

    /**
     * @param array<string, bool> $config
     */
    #[DataProvider('passwordChangeCases')]
    public function testProfilePasswordChange(array $config, bool $ldap_account, bool $allowed): void
    {
        $this->setUpCase($config, $ldap_account);

        $this->actingAs($this->user)
            ->get('/profile')
            ->assertOk()
            ->assertViewHas('props', fn (array $props): bool => $props['can-change-password'] === $allowed);

        $_POST = [
            'oldpasswd' => self::OLD_PASSWORD,
            'passwd' => self::NEW_PASSWORD,
            'passwd2' => self::NEW_PASSWORD,
            'updatepassword' => 'Update Password',
        ];
        $response = $this->actingAs($this->user)->post('/profile');

        $this->user->refresh();
        if ($allowed) {
            $response->assertSee('Your password has been updated');
            $this->assertTrue(password_verify(self::NEW_PASSWORD, $this->user->password));
        } else {
            $response->assertSee('Password changes are disabled');
            $this->assertTrue(password_verify(self::OLD_PASSWORD, $this->user->password));
        }
    }

    public function testUsersCannotChangeOtherUsersPasswords(): void
    {
        $admin = User::factory()->adminUser()->create();

        $this->assertTrue($this->user->can('changePassword', $this->user));
        $this->assertFalse($admin->can('changePassword', $this->user));
    }

    /**
     * @param array<string, bool> $config
     */
    #[DataProvider('passwordChangeCases')]
    public function testExpiredPasswordOnlyEnforcedWhenPasswordCanBeChanged(array $config, bool $ldap_account, bool $allowed): void
    {
        $this->setUpCase($config, $ldap_account);
        config(['cdash.password.expires' => 1]);

        $response = $this->actingAs($this->user)->get('/projects');
        if ($allowed) {
            $response->assertRedirect('/profile?password_expired=1');
        } else {
            $response->assertOk();
        }

        $this->actingAs($this->user)
            ->get('/profile?password_expired=1')
            ->assertOk()
            ->assertViewHas('props', fn (array $props): bool => $props['error'] === ($allowed ? 'Password has expired' : ''));
    }
}
