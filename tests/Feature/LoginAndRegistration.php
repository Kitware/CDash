<?php

namespace Tests\Feature;

use App\Listeners\Saml2Login as Saml2LoginListener;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Mockery;
use Slides\Saml2\Events\SignedIn as Saml2SignedInEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LoginAndRegistration extends TestCase
{
    protected static string $email = 'logintest@user.com';
    protected static string $password = '54321';

    protected function setUp() : void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
    }

    protected function tearDown(): void
    {
        /**
          * The lack of a call to parent::tearDown() here is intentional.
          * Otherwise tearDownAfterClass() fails with:
          * "Target class [config] does not exist."
          */
    }

    public static function tearDownAfterClass() : void
    {
        $user = User::where('email', LoginAndRegistration::$email)->first();
        if ($user !== null) {
            DB::table('password')->where('userid', $user->id)->delete();
            $user->delete();
        }
        parent::tearDownAfterClass();
    }

    public function testCanViewLoginForm() : void
    {
        // Verify that the normal login form is shown by default.
        $response = $this->get('/login');
        $response->assertOk();
        $response->assertSeeText('Email:');
    }

    public function testRegisterUser() : void
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
        $this->assertDatabaseHas('user', ['email' => LoginAndRegistration::$email]);
    }

    public function testUserCanLoginWithCorrectCredentials() : void
    {
        // Verify that users can login with their username and password.
        $response = $this->post('/login', [
            'email' => LoginAndRegistration::$email,
            'password' => LoginAndRegistration::$password,
        ]);
        $user = User::where('email', LoginAndRegistration::$email)->first();
        $this->assertModelExists($user);
        $this->assertAuthenticatedAs($user);
    }

    public function testUserCannotLoginWithIncorrectCredentials() : void
    {
        // Test the incorrect password workflow.
        $response = $this->post('/login', [
            'email' => LoginAndRegistration::$email,
            'password' => 'not_the_right_password',
        ]);
        $response->assertStatus(401);
        $this->assertGuest();
    }

    public function testDisabledLoginForm() : void
    {
        // Disable username+password authentication and verify that the
        // form is no longer displayed.
        config(['auth.username_password_authentication_enabled' => false]);
        $response = $this->get('/login');
        $response->assertOk();
        $response->assertDontSeeText('Email:');
    }

    public function testUserCannotLoginWithDisabledLoginForm() : void
    {
        // Verify that we can't login by POSTing to /login when the
        // relevant config setting is disabled.
        config(['auth.username_password_authentication_enabled' => false]);
        $response = $this->post('/login', [
            'email' => LoginAndRegistration::$email,
            'password' => LoginAndRegistration::$password,
        ]);
        $this->assertGuest();
    }

    public function testLocalView() : void
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

    public function testSaml2() : void
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
            'name_id_format' => 'persistent'
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
     * @throws \LogicException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws HttpException
     */
    public function testSaml2LoginListener() : void
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
        } catch (\Throwable $e) {
        }
        self::assertEquals(new HttpException(400, 'Invalid SAML2 message ID'), $e);
        Cache::delete('saml-message-id-12345');

        // Verify unknown user case.
        config(['saml2.autoregister_new_users' => false]);
        try {
            $sut->handle($event);
        } catch (\Throwable $e) {
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
}
