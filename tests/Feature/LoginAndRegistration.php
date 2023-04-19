<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
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
}
