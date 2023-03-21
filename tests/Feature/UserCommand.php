<?php

namespace Tests\Feature;

use App\Models\User;
use CDash\Database;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class UserCommand extends TestCase
{
    protected $user;

    /**
     * Feature test for the build:remove artisan command.
     *
     * @return void
     */
    public function testUserCommand()
    {
        // Make sure the user we're about to create doesn't already exist.
        $email = 'test-user-command@localtest.com';
        $this->user = User::where('email', $email)->first();
        if ($this->user !== null) {
            $this::fail('User already exists');
        }

        // Cover errors for not providing enough info to create the user.
        $params = ['--email' => $email];
        Artisan::call('user:save', $params);
        $found = trim(Artisan::output());
        $this::assertEquals('You must specify the --firstname option when creating a new user', $found);

        $params['--firstname'] = 'UserCommand';
        Artisan::call('user:save', $params);
        $found = trim(Artisan::output());
        $this::assertEquals('You must specify the --lastname option when creating a new user', $found);

        $params['--lastname'] = 'Tester';
        Artisan::call('user:save', $params);
        $found = trim(Artisan::output());
        $this::assertEquals('You must specify the --institution option when creating a new user', $found);

        $params['--institution'] = 'CDash';
        Artisan::call('user:save', $params);
        $found = trim(Artisan::output());
        $this::assertEquals('You must specify the --password option when creating a new user', $found);

        // Create the user and verify that it exists now.
        $params['--password'] = 'UserCommandTester';
        Artisan::call('user:save', $params);
        $this->user = User::where('email', $email)->first();
        if ($this->user === null) {
            $this::fail('User was not created successfully');
        }

        // Update the user.
        $params['--institution'] = 'CTest';
        $params['--admin'] = 1;
        Artisan::call('user:save', $params);

        // Verify that changes were made successfully.
        $this->user = User::where('email', $email)->first();
        $this::assertEquals('CTest', $this->user->institution);
        $this::assertEquals(1, $this->user->admin);

        // Delete the user.
        Artisan::call('user:remove', $params = ['--email' => $email]);

        // Verify that the user no longer exists.
        $this->user = User::where('email', $email)->first();
        if ($this->user !== null) {
            $this::fail('User still exists after remove');
        }
        $this->user = null;
    }

    public function tearDown() : void
    {
        if ($this->user) {
            $this->user->delete();
        }

        parent::tearDown();
    }
}
