<?php

namespace Tests\Browser\Pages;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

class ManageOverviewPageTest extends BrowserTestCase
{
    use CreatesProjects;

    private Project $project;
    private User $admin;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->admin = User::factory()->adminUser()->create();
    }

    public function tearDown(): void
    {
        try {
            // Log out before the user is deleted below.
            $this->browse(fn (Browser $browser) => $browser->logout());
        } finally {
            // Deleting a project deletes its overview layout.
            $this->project->delete();
            $this->admin->delete();
        }

        parent::tearDown();
    }

    public function testIsProtectedByLogin(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/manageOverview.php?projectid={$this->project->id}")
                ->waitForTextIn('#subheadername', 'Login');
        });
    }

    public function testSavesLayout(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->admin)
                ->visit("/manageOverview.php?projectid={$this->project->id}")
                ->waitFor('#newBuildColumn option[label="Experimental"]')
                ->click('#newBuildColumn option[label="Experimental"]')
                ->click('#addBuildColumn')
                ->assertSeeIn('#buildSortable', 'Experimental')
                ->click('#saveLayout')
                ->waitFor('#loading[src="img/check.gif"]')

                ->clickLink('Go to overview')
                ->waitForLocation('/overview.php')
                ->waitForTextIn('#app table', 'Experimental')
                ->assertQueryStringHas('project', $this->project->name)
                ->assertSeeIn('#app table', 'Nightly');
        });

        self::assertSame(
            ['Nightly', 'Experimental'],
            DB::table('overview_components')
                ->join('buildgroup', 'buildgroup.id', '=', 'overview_components.buildgroupid')
                ->where('overview_components.projectid', $this->project->id)
                ->orderBy('overview_components.position')
                ->pluck('buildgroup.name')
                ->all(),
        );
    }
}
