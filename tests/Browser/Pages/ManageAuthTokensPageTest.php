<?php

namespace Tests\Browser\Pages;

use App\Enums\AuthTokenScope;
use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;

class ManageAuthTokensPageTest extends BrowserTestCase
{
    /**
     * @var array<User>
     */
    private array $users = [];

    public function tearDown(): void
    {
        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->users = [];

        parent::tearDown();
    }

    public function testNeverExpiringToken(): void
    {
        $this->users['admin'] = User::factory()->adminUser()->create();
        $description = Str::uuid()->toString();

        AuthToken::create([
            'hash' => Str::uuid()->toString(),
            'userid' => $this->users['admin']->id,
            'expires' => '9999-01-01 00:00:00',
            'description' => $description,
            'scope' => AuthTokenScope::FULL_ACCESS,
        ]);

        $this->browse(function (Browser $browser) use ($description): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/authtokens/manage')
                ->waitForTextIn('@data-table', $description)
                ->assertSeeIn('@data-table', 'Never')
            ;
        });
    }
}
