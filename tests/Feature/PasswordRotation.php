<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordRotation extends TestCase
{
    use DatabaseTransactions;

    protected ?User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = null;
    }

    protected function tearDown(): void
    {
        $this->user?->delete();
        parent::tearDown();
    }

    /**
     * Feature test for our password rotation functionality.
     */
    public function testPasswordRotation(): void
    {
        URL::forceRootUrl('http://localhost');

        // Enable password rotation.
        config(['cdash.password.expires' => 1]);

        // Create a user.
        $post_data = [
            'fname' => 'Jane',
            'lname' => 'Smith',
            'email' => 'jane@smith',
            'password' => '12345',
            'password_confirmation' => '12345',
            'institution' => 'me',
            'sent' => 'Register',
            'url' => 'catchbot',
        ];

        $this->post(route('register'), $post_data);
        $this->assertDatabaseHas('users', ['email' => 'jane@smith']);

        // Get the id for this user.
        $this->user = User::where('email', 'jane@smith')->firstOrFail();
        $this::assertEquals('me', $this->user->institution);

        // Make the password too old.
        $this->user->update([
            'password_updated_at' => Carbon::parse('2011-07-22 15:37:57'),
        ]);

        // Make sure we get redirected.
        $response = $this->actingAs($this->user)->get('/viewProjects.php');
        $response->assertRedirect('/profile?password_expired=1');

        // Fail to change due to re-using the same password.
        $_POST = [
            'oldpasswd' => '12345',
            'passwd' => '12345',
            'passwd2' => '12345',
            'updatepassword' => 'Update Password',
        ];
        $response = $this->actingAs($this->user)->post('/profile');
        $response->assertSee('New password matches old password.');

        // Successfully change password twice.
        $_POST = [
            'oldpasswd' => '12345',
            'passwd' => 'qwert',
            'passwd2' => 'qwert',
            'updatepassword' => 'Update Password',
        ];
        $response = $this->actingAs($this->user)->post('/profile');
        $response->assertSee('Your password has been updated');

        $_POST = [
            'oldpasswd' => 'qwert',
            'passwd' => 'asdfg',
            'passwd2' => 'asdfg',
            'updatepassword' => 'Update Password',
        ];
        $response = $this->actingAs($this->user)->post('/profile');
        $response->assertSee('Your password has been updated');
    }
}
