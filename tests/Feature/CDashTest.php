<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * A general place to test the CDash installation on-top of Laravel
 *
 * Class CDashTest
 */
class CDashTest extends TestCase
{
    public function testCDashBasicRequest(): void
    {
        URL::forceRootUrl('http://localhost');
        $this->get('/login')->assertStatus(200);
    }

    public function testCDashReturnsNotFoundGivenPathDoesNotExist(): void
    {
        URL::forceRootUrl('http://localhost');
        $this->get('/nope-not-a-uri')
            ->assertStatus(404);
    }

    public function testOverrideLoginField(): void
    {
        URL::forceRootUrl('http://localhost');
        Config::set('cdash.login_field', 'User');
        $this->get('/login')
            ->assertSeeText('User')
            ->assertDontSeeText('Email');
    }

    public function testViewProjectsRedirectNoPublicProjects(): void
    {
        // If there are no public projects to be shown on the view projects page, we should redirect to the login page.
        URL::forceRootUrl('http://localhost');
        $this->get('/projects')->assertRedirect('/login');

        $normal_user = User::factory()->create();
        $this->actingAs($normal_user)->get('/projects')->assertOk();
        $normal_user->delete();
    }
}
