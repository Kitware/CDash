<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileEmailChangeTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        $_POST = [];

        parent::tearDown();
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
        $new_email = Str::uuid()->toString() . '@example.com';

        $this->actingAs($this->user)
            ->get('/profile')
            ->assertOk()
            ->assertViewHas('props', fn (array $props): bool => $props['can-change-email'] === $allowed);

        $_POST = [
            'fname' => 'New',
            'lname' => 'Name',
            'email' => $new_email,
            'institution' => 'Kitware',
            'updateprofile' => 'Update Profile',
        ];
        $this->actingAs($this->user)->post('/profile')->assertSee('Your profile has been updated');

        $this->user->refresh();
        self::assertSame($allowed ? $new_email : $old_email, $this->user->email);
        self::assertSame(['New', 'Name', 'Kitware'], [$this->user->firstname, $this->user->lastname, $this->user->institution]);
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
    }

    public function testUsersCannotChangeOtherUsersEmailAddresses(): void
    {
        $admin = User::factory()->adminUser()->create();

        $this->assertTrue($this->user->can('changeEmail', $this->user));
        $this->assertFalse($admin->can('changeEmail', $this->user));
    }
}
