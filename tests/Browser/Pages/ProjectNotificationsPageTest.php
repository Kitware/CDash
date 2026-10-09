<?php

namespace Tests\Browser\Pages;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

class ProjectNotificationsPageTest extends BrowserTestCase
{
    use CreatesProjects;

    private Project $project;
    private User $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->project = $this->makePublicProject();
        $this->project->users()->attach($this->user, [
            'role' => ProjectRole::USER,
            'emailtype' => 1,
            'emailcategory' => 2 + 4,
            'emailsuccess' => false,
            'emailmissingsites' => true,
        ]);
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->user->delete();

        parent::tearDown();
    }

    public function testIsProtectedByLogin(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/projects/{$this->project->id}/notifications")
                ->assertUrlIs(config('app.url') . '/login');
        });
    }

    public function testHeaderLinksToPage(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->user)
                ->visit("/projects/{$this->project->id}/members")
                ->waitFor('@project-members-page')
                ->assertAttribute('a[href$="/notifications"]', 'href', url("/projects/{$this->project->id}/notifications"));
        });
    }

    public function testShowsAndUpdatesPreferences(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->user)
                ->visit("/projects/{$this->project->id}/notifications")
                ->waitFor('@project-notifications-page')
                ->assertMissing('@message')
                ->assertMissing('@project-emails-disabled-warning')
                ->assertRadioSelected('emailtype', '1')
                ->assertNotChecked('@email-success')
                ->assertChecked('@email-missing-sites')
                ->assertChecked('@email-category-update')
                ->assertChecked('@email-category-configure')
                ->assertNotChecked('@email-category-warning')
                ->assertNotChecked('@email-category-error')
                ->assertNotChecked('@email-category-test')
                ->assertNotChecked('@email-category-dynamicanalysis')
                ->assertMissing('@unsaved-changes')

                ->radio('emailtype', '2')
                ->check('@email-success')
                ->uncheck('@email-missing-sites')
                ->uncheck('@email-category-update')
                ->check('@email-category-test')
                ->assertVisible('@unsaved-changes')
                ->click('@save-button')

                ->waitForTextIn('@message', 'Your notification preferences have been updated.')
                ->assertRadioSelected('emailtype', '2')
                ->assertChecked('@email-success')
                ->assertNotChecked('@email-missing-sites')
                ->assertNotChecked('@email-category-update')
                ->assertChecked('@email-category-configure')
                ->assertChecked('@email-category-test')
                ->assertMissing('@unsaved-changes');
        });

        $row = DB::table('user2project')
            ->where('userid', $this->user->id)
            ->where('projectid', $this->project->id)
            ->first();
        self::assertNotNull($row);
        self::assertSame(2, (int) $row->emailtype);
        self::assertSame(4 + 32, (int) $row->emailcategory);
        self::assertSame(1, (int) $row->emailsuccess);
        self::assertSame(0, (int) $row->emailmissingsites);
    }

    public function testShowsWarningWhenProjectEmailsDisabled(): void
    {
        $this->project->emailbrokensubmission = false;
        $this->project->save();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->user)
                ->visit("/projects/{$this->project->id}/notifications")
                ->waitFor('@project-emails-disabled-warning')
                ->assertSeeIn('@project-emails-disabled-warning', 'Contact the project administrator.');
        });

        $this->project->users()->updateExistingPivot($this->user->id, ['role' => ProjectRole::ADMINISTRATOR]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/projects/{$this->project->id}/notifications")
                ->waitFor('@project-emails-disabled-warning')
                ->assertSeeLink('Change the project settings.');
        });
    }
}
