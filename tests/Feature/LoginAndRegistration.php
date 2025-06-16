<?php

namespace Tests\Feature;

use App\Listeners\Saml2Login as Saml2LoginListener;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use LogicException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\SimpleCache\InvalidArgumentException;
use Slides\Saml2\Events\SignedIn as Saml2SignedInEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;
use Throwable;

class LoginAndRegistration extends TestCase
{
    use CreatesUsers;

    protected static string $email = 'logintest@user.com';
    protected static string $password = '54321';

    protected static string $blockedEmail = 'disabledRegistration@user.com';

    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
    }

    protected function tearDown(): void
    {
        User::where('email', LoginAndRegistration::$email)->first()?->delete();
        $this->user?->delete();

        parent::tearDown();
    }

    public function testCanViewLoginForm(): void
    {
        // Verify that the normal login form is shown by default.
        $response = $this->get('/login');
        $response->assertOk();
        $response->assertSeeText('Email:');
    }

    public function testRegisterUser(): void
    {
        // Create a user by filling out the registration form.
        $post_data = [
            'fname' => 'Test',
            'lname' => 'User',
            'email' => LoginAndRegistration::$email,
            'password' => LoginAndRegistration::$password,
            'password_confirmation' => LoginAndRegistration::$password,
            'institution' => 'home',
            'sent' => 'Register',
            'url' => 'catchbot',
        ];
        $this->post(route('register'), $post_data);

        // Verify that it really landed in the database.
        $this->assertDatabaseHas('users', ['email' => LoginAndRegistration::$email]);
    }

    public function testUserCanLoginWithCorrectCredentials(): void
    {
        $this->user = $this->makeNormalUser(password: '12345');
        $this->assertModelExists($this->user);

        // Verify that users can login with their username and password.
        $response = $this->post('/login', [
            'email' => $this->user?->email,
            'password' => '12345',
        ]);
        $this->assertModelExists($this->user);
        $this->assertAuthenticatedAs($this->user);
    }

    public function testUserCannotLoginWithIncorrectCredentials(): void
    {
        $this->user = $this->makeNormalUser(password: '12345');

        // Test the incorrect password workflow.
        $response = $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'not_the_right_password',
        ]);
        $response->assertStatus(401);
        $this->assertGuest();
    }

    public function testDisabledLoginForm(): void
    {
        // Disable username+password authentication and verify that the
        // form is no longer displayed.
        config(['cdash.username_password_authentication_enabled' => false]);
        $response = $this->get('/login');
        $response->assertOk();
        $response->assertDontSeeText('Email:');
    }

    public function testUserCannotLoginWithDisabledLoginForm(): void
    {
        $this->user = $this->makeNormalUser(password: '12345');

        // Verify that we can't login by POSTing to /login when the
        // relevant config setting is disabled.
        config(['cdash.username_password_authentication_enabled' => false]);
        $response = $this->post('/login', [
            'email' => $this->user->email,
            'password' => '12345',
        ]);
        $this->assertGuest();
    }

    public function testLocalView(): void
    {
        // Verify that custom text does not appear when the local view is not in place.
        $response = $this->get('/login');
        $response->assertOk();
        $response->assertDontSeeText('My custom text');

        // Verify that the local/login.blade.php template is rendered if it exists.
        $tmp_view_path = resource_path('views/local/login.blade.php');
        file_put_contents($tmp_view_path, '<p>My custom text</p>');

        $response = $this->get('/login');
        $response->assertOk();
        $response->assertSeeText('My custom text');

        unlink($tmp_view_path);
    }

    public function testSaml2(): void
    {
        // Verify that SAML2 login fails when disabled.
        $response = $this->post('/saml2/login');
        $response->assertStatus(500);
        $response->assertSeeText('SAML2 login is not enabled');

        // Verify that the SAML2 button doesn't appear by default.
        $response = $this->get('/login');
        $response->assertDontSeeText('SAML2');

        // Enable SAML2, verify the button appears.
        config(['saml2.enabled' => true]);
        $response = $this->get('/login');
        $response->assertSeeText('SAML2');

        // Verify that changing button text works.
        config(['saml2.login_text' => 'my custom login']);
        $response = $this->get('/login');
        $response->assertSeeText('my custom login');

        // Verify that login fails without a saml2_tenant.
        $response = $this->post('/saml2/login');
        $response->assertStatus(500);
        $response->assertSeeText('SAML2 tenant not found');

        // Create a SAML2 tenant.
        $saml_uuid = (string) Str::uuid();
        $saml2_tenant_name = 'saml2_client_for_testing';
        $saml2_tenant_uri = 'https://cdash.org/fake-saml2-idp/asdf';
        $params = [
            'uuid' => $saml_uuid,
            'key' => $saml2_tenant_name,
            'idp_entity_id' => $saml2_tenant_uri,
            'idp_login_url' => "$saml2_tenant_uri/login",
            'idp_logout_url' => "$saml2_tenant_uri/logout",
            'idp_x509_cert' => base64_encode('asdf'),
            'metadata' => '{}',
            'name_id_format' => 'persistent',
        ];
        $saml2_tenant_id = DB::table('saml2_tenants')->insertGetId($params);

        // Verify that SAML2 login redirects as expected.
        $response = $this->post('/saml2/login');
        $response->assertRedirectContains("/saml2/{$saml_uuid}/login?returnTo=");

        // Delete SAML2 tenant.
        DB::table('saml2_tenants')->delete($saml2_tenant_id);
    }

    /**
     * Test SAML2 authentication
     *
     * @throws LogicException
     * @throws InvalidArgumentException
     * @throws HttpException
     */
    public function testSaml2LoginListener(): void
    {
        // Setup mock objects.
        $mock_base_auth = Mockery::mock('OneLogin\Saml2\Auth');
        $mock_base_auth->shouldReceive('getLastAssertionNotOnOrAfter')->andReturn(5);

        $mock_saml2_auth = Mockery::mock('Slides\Saml2\Auth');
        $mock_saml2_auth->shouldReceive('getLastMessageId')->andReturn('12345');
        $mock_saml2_auth->shouldReceive('getBase')->andReturn($mock_base_auth);

        $mock_saml2_user = Mockery::mock('Slides\Saml2\Saml2User');
        $mock_saml2_user->shouldReceive('getUserId')->andReturn('logintestsaml@user.com');

        // Create event and listener.
        $event = new Saml2SignedInEvent($mock_saml2_user, $mock_saml2_auth);
        $sut = new Saml2LoginListener();

        // Verify replay attack protection.
        $e = null;
        Cache::put('saml-message-id-12345', true, 5);
        try {
            $sut->handle($event);
        } catch (Throwable $e) {
        }
        self::assertEquals(new HttpException(400, 'Invalid SAML2 message ID'), $e);
        Cache::delete('saml-message-id-12345');

        // Verify unknown user case.
        config(['saml2.autoregister_new_users' => false]);
        try {
            $sut->handle($event);
        } catch (Throwable $e) {
        }
        self::assertEquals(new HttpException(401), $e);
        Cache::delete('saml-message-id-12345');

        // Verify automatic registration.
        config(['saml2.autoregister_new_users' => true]);
        $sut->handle($event);
        $user = User::where('email', 'logintestsaml@user.com')->first();
        $this->assertModelExists($user);
        $this->assertAuthenticatedAs($user);
        Cache::delete('saml-message-id-12345');

        // Verify regular SAML2 login.
        Auth::logout();
        $this->assertGuest();
        config(['saml2.autoregister_new_users' => false]);
        $sut->handle($event);
        $this->assertAuthenticatedAs($user);
        Cache::delete('saml-message-id-12345');

        // Delete user created by this test.
        $user->delete();

        Mockery::close();
    }

    public function testPingIdentity(): void
    {
        // Verify that the PingIdentity button doesn't appear by default.
        $response = $this->get('/login');
        $response->assertDontSeeText('PingIdentity');

        // Enable PingIdentity, verify the button appears.
        config(['services.pingidentity.enable' => true]);
        $response = $this->get('/login');
        $response->assertSeeText('PingIdentity');
    }

    /**
     * @return array<array<string>>
     */
    public static function oauthProviders(): array
    {
        return [
            ['github'],
            ['gitlab'],
            ['google'],
            ['pingidentity'],
        ];
    }

    #[DataProvider('oauthProviders')]
    public function testCustomOauthDisplayNames(string $serviceName): void
    {
        // Enable PingIdentity, verify the button appears.
        config(["services.$serviceName.enable" => true]);
        $displayName = Str::uuid()->toString();
        config(["services.$serviceName.display_name" => $displayName]);
        $response = $this->get('/login');
        $response->assertSeeText($displayName);
    }

    /**
     * Test PingIdentity authentication
     */
    public function testPingIdentityProvider(): void
    {
        // Stolen from: https://laracasts.com/discuss/channels/testing/testing-laravel-socialite-callback
        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getId')
        ->andReturn(1234567890)
        ->shouldReceive('getEmail')
        ->andReturn('cdash@test.com')
        ->shouldReceive('getNickname')
        ->andReturn('Pseudo')
        ->shouldReceive('getName')
        ->andReturn('Arlette Laguiller')
        ->shouldReceive('getAvatar')
        ->andReturn('https://en.gravatar.com/userimage');

        $provider = Mockery::mock('Laravel\Socialite\PingIdentity\Provider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('pingidentity')->andReturn($provider);

        $response = $this->get('auth/pingidentity/callback');
        $response->assertRedirect('/profile');
    }

    public function testNoFullNamePingIdentityProvider(): void
    {
        // Stolen from: https://laracasts.com/discuss/channels/testing/testing-laravel-socialite-callback
        $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
        $abstractUser->shouldReceive('getId')
        ->andReturn(1234567890)
        ->shouldReceive('getEmail')
        ->andReturn('cdash@test.com')
        ->shouldReceive('getNickname')
        ->andReturn('Pseudo')
        ->shouldReceive('getName')
        ->andReturn('Pseudo')
        ->shouldReceive('getAvatar')
        ->andReturn('https://en.gravatar.com/userimage');

        $provider = Mockery::mock('Laravel\Socialite\PingIdentity\Provider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('pingidentity')->andReturn($provider);

        $response = $this->get('auth/pingidentity/callback');
        $response->assertRedirect('/profile');
    }

    public function testRegisterUserWhenDisabled(): void
    {
        // Create a user by sending proper data

        config(['auth.user_registration_form_enabled' => false]);
        $post_data = [
            'fname' => 'Test',
            'lname' => 'User',
            'email' => LoginAndRegistration::$blockedEmail,
            'password' => LoginAndRegistration::$password,
            'password_confirmation' => LoginAndRegistration::$password,
            'institution' => 'home',
            'sent' => 'Register',
            'url' => 'catchbot',
        ];
        $this->post(route('register'), $post_data);

        // Verify that nothing was added to the database
        $this->assertDatabaseMissing('users', ['email' => LoginAndRegistration::$blockedEmail]);
    }

    public function testDisabledRegistrationForm(): void
    {
        // Disable username+password authentication and verify that the
        // form is no longer displayed.
        config(['auth.user_registration_form_enabled' => false]);
        $response = $this->get('/register');
        $response->assertStatus(404);
    }
}
