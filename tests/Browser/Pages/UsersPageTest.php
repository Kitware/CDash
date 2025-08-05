<?php

namespace Tests\Browser\Pages;

use App\Enums\GlobalRole;
use App\Models\GlobalInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesUsers;

class UsersPageTest extends BrowserTestCase
{
    use CreatesUsers;

    /**
     * @var array<User>
     */
    private array $users = [];

    /**
     * @var array<GlobalInvitation>
     */
    private array $invitations = [];

    public function tearDown(): void
    {
        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->users = [];

        foreach ($this->invitations as $invitation) {
            $invitation->delete();
        }
        $this->invitations = [];

        parent::tearDown();
    }

    private function createInvitation(?string $password = null): GlobalInvitation
    {
        if ($password === null) {
            $password = Str::password();
        }

        /** @var GlobalInvitation $invitation */
        $invitation = GlobalInvitation::create([
            'email' => fake()->unique()->email(),
            'invited_by_id' => $this->users['admin']->id,
            'role' => GlobalRole::USER,
            'invitation_timestamp' => Carbon::now(),
            'password' => Hash::make($password),
        ]);
        $this->invitations[] = $invitation;

        return $invitation;
    }

    public function testAdminsCanSeeInviteUsersButton(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/users')
                ->whenAvailable('@users-page', function (Browser $browser): void {
                    $browser->assertVisible('@invite-users-button');
                });
        });
    }

    public function testInviteUsersButtonNotVisibleToRegularUsers(): void
    {
        $this->users['normal'] = $this->makeNormalUser();
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['normal'])
                ->visit('/users')
                ->whenAvailable('@users-page', function (Browser $browser): void {
                    $browser->assertMissing('@invite-users-button');
                });
        });
    }

    public function testInviteUsersButtonNotVisibleWhenSignedOut(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/users')
                ->whenAvailable('@users-page', function (Browser $browser): void {
                    $browser->assertMissing('@invite-users-button');
                });
        });
    }

    public function testAdminsCanSeeInvitationsTable(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/users')
                ->whenAvailable('@users-page', function (Browser $browser): void {
                    $browser->waitFor('@invitations-table')
                        ->assertVisible('@invitations-table');
                });
        });
    }

    public function testRegularUsersCannotSeeInvitationsTable(): void
    {
        $this->users['normal'] = $this->makeNormalUser();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['normal'])
                ->visit('/users')
                ->whenAvailable('@users-page', function (Browser $browser): void {
                    $browser->assertMissing('@invitations-table');
                });
        });
    }

    public function testAnonymousUsersCannotSeeInvitationsTable(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/users')
                ->whenAvailable('@users-page', function (Browser $browser): void {
                    $browser->assertMissing('@invitations-table');
                });
        });
    }

    public function testFullInvitationWorkflow(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $fakeEmail = fake()->unique()->email();

        $this->browse(function (Browser $browser) use ($fakeEmail): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/users')
                ->waitFor('@users-page')
                ->click('@invite-users-button')
                ->waitFor('@invite-users-modal')
                ->assertInputValue('@invite-users-modal-email', '')
                ->type('@invite-users-modal-email', $fakeEmail)
                ->click('@invite-users-modal-cancel-button')
                ->waitUntilMissingText($fakeEmail)
                ->click('@invite-users-button')
                ->assertInputValue('@invite-users-modal-email', $fakeEmail)
                ->click('@invite-users-modal-invite-button')
                ->waitForText($fakeEmail)
                ->waitForText($this->users['admin']->firstname)
                ->waitForText($this->users['admin']->lastname)
                ->refresh()
                ->waitForText($fakeEmail)
                ->waitForText($this->users['admin']->firstname)
                ->waitForText($this->users['admin']->lastname)
                ->click('@invitations-table-row @revoke-invitation-button')
                ->waitUntilMissingText($fakeEmail)
            ;
        });
    }

    public function testInvitationTablePagination(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        for ($i = 0; $i < 105; $i++) {
            $this->createInvitation();
        }

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/users')
                ->whenAvailable('@invitations-table', function (Browser $browser): void {
                    foreach ($this->invitations as $invitation) {
                        $browser->waitForText($invitation->email);
                    }
                    $browser->assertSee($this->invitations[0]->email);  // Add an assertion to suppress warnings...
                });
        });
    }

    public function testUsersTablePagination(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        for ($i = 0; $i < 105; $i++) {
            $this->users[] = $this->makeNormalUser();
        }

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/users')
                ->whenAvailable('@users-table', function (Browser $browser): void {
                    foreach ($this->users as $user) {
                        $browser->waitForText($user->email);
                    }
                    $browser->assertSee($this->users['admin']->email);  // Add an assertion to suppress warnings...
                });
        });
    }

    public function testAdminsCannotChangeOwnRole(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/users')
                ->waitFor('@role-text-' . $this->users['admin']->id)
                ->assertSeeIn('@role-text-' . $this->users['admin']->id, 'Administrator')
                ->assertMissing('@role-select-' . $this->users['admin']->id);
        });
    }

    public function testAdminsCanChangeRoleForOtherUsers(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $this->users['normal'] = $this->makeNormalUser();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/users')
                ->waitFor('@role-select-' . $this->users['normal']->id)
                ->assertValue('@role-select-' . $this->users['normal']->id, 'USER')
                ->assertMissing('@role-text-' . $this->users['normal']->id)
                ->select('@role-select-' . $this->users['normal']->id, 'ADMINISTRATOR')
                ->refresh()
                ->waitFor('@role-select-' . $this->users['normal']->id)
                ->assertValue('@role-select-' . $this->users['normal']->id, 'ADMINISTRATOR')
                ->assertMissing('@role-text-' . $this->users['normal']->id)
            ;
        });
    }

    public function testNormalUsersCannotChangeRole(): void
    {
        $this->users['normal1'] = $this->makeNormalUser();
        $this->users['normal2'] = $this->makeNormalUser();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['normal1'])
                ->visit('/users')
                ->waitFor('@role-text-' . $this->users['normal2']->id)
                ->assertSeeIn('@role-text-' . $this->users['normal2']->id, 'User')
                ->assertMissing('@role-select-' . $this->users['normal2']->id);
        });
    }

    public function testAnonymousUsersCannotChangeRole(): void
    {
        $this->users['normal'] = $this->makeNormalUser();

        $this->browse(function (Browser $browser): void {
            $browser
                ->visit('/users')
                ->waitFor('@role-text-' . $this->users['normal']->id)
                ->assertSeeIn('@role-text-' . $this->users['normal']->id, 'User')
                ->assertMissing('@role-select-' . $this->users['normal']->id);
        });
    }

    public function testRemoveUserButtonAppearsForAdmins(): void
    {
        $this->users['admin1'] = $this->makeAdminUser();
        $this->users['admin2'] = $this->makeAdminUser();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin1'])
                ->visit('/users')
                ->whenAvailable('@users-table', function (Browser $browser): void {
                    $browser->assertVisible('@remove-user-button-' . $this->users['admin2']->id);
                });
        });
    }

    public function testRegularUsersDontSeeRemoveUserButton(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $this->users['normal'] = $this->makeNormalUser();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['normal'])
                ->visit('/users')
                ->whenAvailable('@users-table', function (Browser $browser): void {
                    $browser->assertMissing('@remove-user-button-' . $this->users['admin']->id);
                });
        });
    }

    public function testAnonymousUsersDontSeeRemoveUserButton(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $this->browse(function (Browser $browser): void {
            $browser->visit('/users')
                ->whenAvailable('@users-table', function (Browser $browser): void {
                    $browser->assertMissing('@remove-user-button-' . $this->users['admin']->id);
                });
        });
    }

    public function testCannotRemoveSelf(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/users')
                ->whenAvailable('@users-table', function (Browser $browser): void {
                    $browser->assertMissing('@remove-user-button-' . $this->users['admin']->id);
                });
        });
    }

    public function testAdminCanRemoveUser(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $this->users['normal'] = $this->makeNormalUser();

        self::assertContains($this->users['normal']->id, User::pluck('id'));

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/users')
                ->waitFor('@remove-user-button-' . $this->users['normal']->id)
                ->click('@remove-user-button-' . $this->users['normal']->id)
                ->waitUntilMissing('@remove-user-button-' . $this->users['normal']->id)
                ->refresh()
                ->waitForText($this->users['admin']->email) // A rudimentary way to check whether all of the users have loaded.
                ->assertMissing('@remove-user-button-' . $this->users['normal']->id)
            ;
        });

        self::assertNotContains($this->users['normal']->id, User::pluck('id'));
    }
}
