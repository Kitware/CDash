<?php

namespace Tests\Browser\Pages;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class ProjectMembersPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesUsers;

    private Project $project;

    /**
     * @var array<User>
     */
    private array $users = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->project->delete();

        foreach ($this->users as $users) {
            $users->delete();
        }
        $this->users = [];
    }

    public function testGlobalAdminsCanSeeInviteMembersButton(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->users['admin'])
                ->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@project-members-page', function (Browser $browser) {
                    $browser->assertVisible('@invite-members-button');
                });
        });
    }

    public function testProjectAdminsCanSeeInviteMembersButton(): void
    {
        $this->users['normal'] = $this->makeNormalUser();

        $this->project
            ->users()
            ->attach($this->users['normal']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_ADMIN,
            ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->users['normal'])
                ->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@project-members-page', function (Browser $browser) {
                    $browser->assertVisible('@invite-members-button');
                });
        });
    }

    public function testProjectUsersCannotSeeInviteMembersButton(): void
    {
        $this->users['normal'] = $this->makeNormalUser();

        $this->project
            ->users()
            ->attach($this->users['normal']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_USER,
            ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->users['normal'])
                ->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@project-members-page', function (Browser $browser) {
                    $browser->assertMissing('@invite-members-button');
                });
        });
    }

    public function testInviteMembersButtonDoesNotAppearWhenSignedOut(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@project-members-page', function (Browser $browser) {
                    $browser->assertMissing('@invite-members-button');
                });
        });
    }

    public function testGlobalAdminsCanSeeInvitationsTable(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->users['admin'])
                ->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@project-members-page', function (Browser $browser) {
                    $browser->waitFor('@invitations-table')
                        ->assertVisible('@invitations-table');
                });
        });
    }

    public function testProjectAdminsCanSeeInvitationsTable(): void
    {
        $this->users['normal'] = $this->makeNormalUser();

        $this->project
            ->users()
            ->attach($this->users['normal']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_ADMIN,
            ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->users['normal'])
                ->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@project-members-page', function (Browser $browser) {
                    $browser->waitFor('@invitations-table')
                        ->assertVisible('@invitations-table');
                });
        });
    }

    public function testProjectMembersCannotSeeInvitationsTable(): void
    {
        $this->users['normal'] = $this->makeNormalUser();

        $this->project
            ->users()
            ->attach($this->users['normal']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_USER,
            ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->users['normal'])
                ->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@project-members-page', function (Browser $browser) {
                    $browser->assertMissing('@invitations-table');
                });
        });
    }

    public function testInvitationsTableDoesNotAppearWhenSignedOut(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@project-members-page', function (Browser $browser) {
                    $browser->assertMissing('@invitations-table');
                });
        });
    }

    public function testFullInvitationWorkflow(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $fakeEmail = fake()->email();

        $this->browse(function (Browser $browser) use ($fakeEmail) {
            $browser->loginAs($this->users['admin'])
                ->visit("/projects/{$this->project->id}/members")
                ->waitFor('@project-members-page')
                ->assertCount('@invitations-table-row', 0)
                ->click('@invite-members-button')
                ->waitFor('@invite-members-modal')
                ->assertInputValue('@invite-members-modal-email', '')
                ->type('@invite-members-modal-email', $fakeEmail)
                ->click('@invite-members-modal-cancel-button')
                ->assertCount('@invitations-table-row', 0)
                ->click('@invite-members-button')
                ->assertInputValue('@invite-members-modal-email', $fakeEmail)
                ->click('@invite-members-modal-invite-button')
                ->waitFor('@invitations-table-row')
                ->assertCount('@invitations-table-row', 1)
                ->assertSeeIn('@invitations-table-row', $fakeEmail)
                ->assertSeeIn('@invitations-table-row', $this->users['admin']->firstname)
                ->assertSeeIn('@invitations-table-row', $this->users['admin']->lastname)
                ->refresh()
                ->waitFor('@invitations-table-row')
                ->assertCount('@invitations-table-row', 1)
                ->assertSeeIn('@invitations-table-row', $fakeEmail)
                ->assertSeeIn('@invitations-table-row', $this->users['admin']->firstname)
                ->assertSeeIn('@invitations-table-row', $this->users['admin']->lastname)
                ->click('@invitations-table-row @revoke-invitation-button')
                ->waitUntilMissing('@invitations-table-row')
                ->assertCount('@invitations-table-row', 0)
                ->refresh()
                ->waitFor('@invitations-table')
                ->waitUntilMissing('@invitations-table-row')
                ->assertCount('@invitations-table-row', 0)
            ;
        });
    }

    public function testInvitationTablePagination(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $email = '';
        for ($i = 0; $i < 120; $i++) {
            $email = fake()->email();  // We store the oldest email to make the following assertions less flaky...
            $this->project->invitations()->create([
                'email' => $email,
                'invited_by_id' => $this->users['admin']->id,
                'role' => ProjectRole::USER,
                'invitation_timestamp' => Carbon::now(),
            ]);
        }

        $this->browse(function (Browser $browser) use ($email) {
            $browser->loginAs($this->users['admin'])
                ->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@invitations-table', function (Browser $browser) use ($email) {
                    $browser->waitForText($email)
                        ->assertCount('@invitations-table-row', 120);
                });
        });
    }

    public function testProjectAdminsCannotChangeOwnRole(): void
    {
        $this->users['normal'] = $this->makeNormalUser();

        $this->project
            ->users()
            ->attach($this->users['normal']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_ADMIN,
            ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->users['normal'])
                ->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@members-table-row', function (Browser $browser) {
                    $browser->assertVisible('@role-text')
                        ->assertMissing('@role-select');
                });
        });
    }

    public function testAdminsCanChangeUserRole(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $this->users['normal'] = $this->makeNormalUser();

        $this->project
            ->users()
            ->attach($this->users['normal']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_USER,
            ]);

        self::assertNotContains($this->users['normal']->id, $this->project->administrators()->pluck('id'));

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->users['admin'])
                ->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@members-table-row', function (Browser $browser) {
                    $browser->assertVisible('@role-select')
                        ->assertMissing('@role-text')
                        ->assertSelected('@role-select', 'USER')
                        ->select('@role-select', 'ADMINISTRATOR')
                        ->refresh()
                        ->waitFor('@role-select')
                        ->assertMissing('@role-text')
                        ->assertSelected('@role-select', 'ADMINISTRATOR')
                    ;
                });
        });

        self::assertContains($this->users['normal']->id, $this->project->administrators()->pluck('id'));
    }

    public function testBasicUsersCannotChangeUserRole(): void
    {
        $this->users['normal1'] = $this->makeNormalUser();
        $this->users['normal2'] = $this->makeNormalUser();

        $this->project
            ->users()
            ->attach($this->users['normal1']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_USER,
            ]);

        $this->project
            ->users()
            ->attach($this->users['normal2']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_USER,
            ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->users['normal1'])
                ->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@members-table-row', function (Browser $browser) {
                    $browser->assertMissing('@role-select')
                        ->assertVisible('@role-text');
                });
        });
    }

    public function testNonMembersCannotChangeUserRole(): void
    {
        $this->users['normal1'] = $this->makeNormalUser();
        $this->users['normal2'] = $this->makeNormalUser();

        $this->project
            ->users()
            ->attach($this->users['normal2']->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_USER,
            ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->users['normal1'])
                ->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@members-table-row', function (Browser $browser) {
                    $browser->assertMissing('@role-select')
                        ->assertVisible('@role-text');
                });
        });
    }

    public function testMembersListPagination(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->users[$i] = $this->makeNormalUser();
            $this->project
                ->users()
                ->attach($this->users[$i]->id, [
                    'emailtype' => 0,
                    'emailcategory' => 0,
                    'emailsuccess' => true,
                    'emailmissingsites' => true,
                    'role' => Project::PROJECT_USER,
                ]);
        }

        $this->browse(function (Browser $browser) {
            $browser->visit("/projects/{$this->project->id}/members")
                ->whenAvailable('@members-table', function (Browser $browser) {
                    // Make sure the pagination has completed before making assertions about the count
                    $browser->waitForText($this->users[119]->firstname . ' ' . $this->users[119]->lastname)
                        ->waitForText($this->users[50]->firstname . ' ' . $this->users[50]->lastname)
                        ->waitForText($this->users[0]->firstname . ' ' . $this->users[0]->lastname)
                        ->assertCount('@members-table-row', 120);
                });
        });
    }

    public function testHandlesMisformattedEmail(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $fakeEmail = fake()->email();

        $this->browse(function (Browser $browser) use ($fakeEmail) {
            $browser->loginAs($this->users['admin'])
                ->visit("/projects/{$this->project->id}/members")
                ->waitFor('@project-members-page')
                ->click('@invite-members-button')
                ->waitFor('@invite-members-modal')
                ->type('@invite-members-modal-email', 'abc')
                ->assertMissing('@invite-members-modal-error-text')
                ->click('@invite-members-modal-invite-button')
                ->waitForTextIn('@invite-members-modal-error-text', 'The email must be a valid email address.')
                ->clear('@invite-members-modal-email')
                ->type('@invite-members-modal-email', $fakeEmail)
                ->click('@invite-members-modal-invite-button')
                ->waitUntilMissing('@invite-members-modal')
                ->waitFor('@invitations-table-row')
                ->assertCount('@invitations-table-row', 1)
                ->assertSeeIn('@invitations-table-row', $fakeEmail)
                ->assertSeeIn('@invitations-table-row', $this->users['admin']->firstname)
                ->assertSeeIn('@invitations-table-row', $this->users['admin']->lastname)
            ;
        });
    }
}
