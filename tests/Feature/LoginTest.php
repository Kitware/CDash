<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginController;
use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    private $url;

    public function setUp()
    {
        parent::setUp();
        $this->url = url('/login');
    }

    public function testLoginGivenInvalidCredentials()
    {
        $post = [
            'email' => 'not.in@database.tld',
            'password' => 'password',
        ];

        $this->post($this->url, $post)
            ->assertViewIs('auth.login')
            ->assertStatus(401)
            ->assertSeeText('These credentials do not match our records');
    }

    public function testLoginGivenInvalidPassword()
    {
        $user = factory(User::class)->create();

        $post = [
            'email' => $user->email,
            'password' => 'password',
        ];

        $this->post($this->url, $post)
            ->assertViewIs('auth.login')
            ->assertStatus(401)
            ->assertSeeText('These credentials do not match our records');
    }

    public function testLoginGivenValidCredentials()
    {
        $user = factory(User::class)->create();

        $post = [
            'email' => $user->email,
            'password' => 'secret',
        ];

        $this->post($this->url, $post)
            ->assertRedirect('/');
    }
}
