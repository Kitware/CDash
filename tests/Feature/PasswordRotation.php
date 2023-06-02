<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordRotation extends TestCase
{
    protected $user;

    protected function setUp() : void
    {
        parent::setUp();
        $this->user = null;
    }

    protected function tearDown() : void
    {
        if ($this->user) {
            DB::table('password')->where('userid', $this->user->id)->delete();
            $this->user->delete();
        }
        parent::tearDown();
    }

    /**
     * Feature test for our password rotation functionality.
     *
     * @return void
     */
    public function testPasswordRotation()
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
        $this->assertDatabaseHas('user', ['email' => 'jane@smith']);

        // Get the id for this user.
        $this->user = User::where('email', 'jane@smith')->first();
        $this::assertEquals('me', $this->user->institution);

        // Make sure the password was recorded for rotation.
        $this->assertDatabaseHas('password', ['userid' => $this->user->id]);

        // Make the password too old.
        DB::table('password')
            ->where('userid', $this->user->id)
            ->update(['date' => '2011-07-22 15:37:57']);

        // Make sure we get redirected.
        $response = $this->actingAs($this->user)->get('/viewProjects.php');
        $response->assertRedirect('editUser.php?password_expired=1');

        // Fail to change due to re-using the same password.
        $_POST = [
            'oldpasswd' => '12345',
            'passwd' => '12345',
            'passwd2' => '12345',
            'updatepassword' => 'Update Password',
        ];
        $response = $this->actingAs($this->user)->post('/editUser.php');
        $response->assertSee('You have recently used this password');

        // Get the current password hash to compare against later.
        $password_hash = DB::table('password')
            ->where('userid', $this->user->id)
            ->value('password');
        $this::assertIsString($password_hash);

        // Enable unique password count.
        config(['cdash.password.unique' => 2]);

        // Successfully change password twice.
        $_POST = [
            'oldpasswd' => '12345',
            'passwd' => 'qwert',
            'passwd2' => 'qwert',
            'updatepassword' => 'Update Password',
        ];
        $response = $this->actingAs($this->user)->post('/editUser.php');
        $response->assertSee('Your password has been updated');

        $_POST = [
            'oldpasswd' => 'qwert',
            'passwd' => 'asdfg',
            'passwd2' => 'asdfg',
            'updatepassword' => 'Update Password',
        ];
        $response = $this->actingAs($this->user)->post('/editUser.php');
        $response->assertSee('Your password has been updated');

        // Make sure the oldest password was deleted since we're only keeping
        // the two most recent entries.
        $password_rows = DB::table('password')
            ->where('userid', $this->user->id)
            ->get();
        $this::assertEquals(2, count($password_rows));
        $this->assertDatabaseMissing('password', ['password' => $password_hash]);

        // Verify that we can set our password back to the original one
        // since it now exceed our unique count of 2.
        $_POST = [
            'oldpasswd' => 'asdfg',
            'passwd' => '12345',
            'passwd2' => '12345',
            'updatepassword' => 'Update Password',
        ];
        $response = $this->actingAs($this->user)->post('/editUser.php');
        $response->assertSee('Your password has been updated');
    }
}
