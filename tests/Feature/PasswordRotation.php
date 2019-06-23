<?php

namespace Tests\Feature;

use App\Password;
use App\User;
use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PasswordRotation extends TestCase
{
    use WithFaker, RefreshDatabase;

    private $url = '/viewProjects.php';

    public function testPasswordExpiredRedirects()
    {
        $created_at = new Carbon('2018-01-01');

        $password = factory(Password::class)->create(['date' => $created_at]);
        $user = User::find($password->userid);
        $this->actingAs($user)
            ->get($this->url)
            ->assertRedirect('/password/reset');
    }
}
