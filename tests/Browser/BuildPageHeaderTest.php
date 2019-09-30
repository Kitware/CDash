<?php

namespace Tests\Browser;

use App\User;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class BuildPageHeaderTest extends DuskTestCase
{
    use WithFaker, DatabaseMigrations;

    /**
     * Test links visible when user *not* logged in
     *
     * @throws \Throwable
     */
    public function testHeaderTopGivenNoAuthenticatedUser()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/build/1')
                ->assertSee('Login')
                ->assertSee('Register')
                ->assertSee('All Dashboards');
        });
    }

    /**
     * Tests links visible when user logged in
     *
     * @throws \Throwable
     */
    public function testHeaderTopGivenAuthenticatedUser()
    {
        $user = factory(User::class)->create([
            'email' => $this->faker->email,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/build/1')
                ->assertSee('My CDash')
                ->assertSee('All Dashboards')
                ->assertSee('Log out');
        });
    }
}
