<?php

namespace Tests\Browser\Pages;

use App\Models\Project;
use App\Models\User;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

class ManageSubProjectPageTest extends BrowserTestCase
{
    use CreatesProjects;

    private Project $project;
    private User $admin;
    private ?Project $otherProject = null;

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
            // Deleting a project deletes its SubProjects and SubProject groups.
            $this->project->delete();
            $this->otherProject?->delete();
            $this->otherProject = null;
            $this->admin->delete();
        }

        parent::tearDown();
    }

    public function testIsProtectedByLogin(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/manageSubProject.php?projectid={$this->project->id}")
                ->waitForTextIn('#subheadername', 'Login');
        });
    }

    public function testNavigatesBetweenProjects(): void
    {
        $otherProject = $this->makePublicProject();
        $this->otherProject = $otherProject;

        $this->browse(function (Browser $browser) use ($otherProject): void {
            $browser->loginAs($this->admin)
                ->visit("/manageSubProject.php?projectid={$this->project->id}")
                ->waitFor("select[name=\"projectSelection\"] option[value=\"{$otherProject->id}\"]")
                ->waitForReload(fn (Browser $browser) => $browser->select('projectSelection', (string) $otherProject->id))
                ->assertPathIs('/manageSubProject.php')
                ->assertQueryStringHas('projectid', (string) $otherProject->id)
                ->waitFor("select[name=\"projectSelection\"] option[value=\"{$this->project->id}\"]")
                ->waitForReload(fn (Browser $browser) => $browser->select('projectSelection', (string) $this->project->id))
                ->assertPathIs('/manageSubProject.php')
                ->assertQueryStringHas('projectid', (string) $this->project->id);
        });
    }

    public function testCreatesSubProjectGroups(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->admin)
                ->visit("/manageSubProject.php?projectid={$this->project->id}")
                ->waitFor('@subproject-groups')
                ->click('@subproject-groups')
                ->type('newgroup', 'group1')
                ->press('Add group')
                ->waitFor('@existing-subproject-groups tbody tr:nth-child(1)')
                ->type('newgroup', 'gorup2') // intentional typo
                ->press('Add group')
                ->waitFor('@existing-subproject-groups tbody tr:nth-child(2)')

                ->refresh()
                ->waitFor('@subproject-groups')
                ->click('@subproject-groups')
                ->waitFor('@existing-subproject-groups');

            self::assertCount(2, $browser->elements('@existing-subproject-groups tbody tr'));
            $browser->assertInputValue('@existing-subproject-groups tbody tr:nth-child(1) input[name="group_name"]', 'group1')
                ->assertInputValue('@existing-subproject-groups tbody tr:nth-child(2) input[name="group_name"]', 'gorup2');
        });

        self::assertSame(['group1', 'gorup2'], $this->project->subProjectGroups()->orderBy('id')->pluck('name')->all());
    }

    public function testModifiesSubProjectGroup(): void
    {
        $this->project->subProjectGroups()->create(['name' => 'group1', 'is_default' => 1, 'position' => 1]);
        $group = $this->project->subProjectGroups()->create(['name' => 'gorup2', 'is_default' => 0, 'position' => 2]);
        $row = "#sortable tr[id=\"{$group->id}\"]";

        $this->browse(function (Browser $browser) use ($group, $row): void {
            $browser->loginAs($this->admin)
                ->visit("/manageSubProject.php?projectid={$this->project->id}")
                ->waitFor('@subproject-groups')
                ->click('@subproject-groups')
                ->waitFor($row)

                // Rename the group, change its threshold, and make it the default group.
                ->type("{$row} input[name=\"group_name\"]", 'group2')
                ->click("{$row} input[name=\"groupRadio\"]")
                ->type("{$row} input[name=\"coverage_threshold\"]", '65')
                ->click("{$row} button")
                ->waitFor("#group_updated_{$group->id}")

                ->refresh()
                ->waitFor('@subproject-groups')
                ->click('@subproject-groups')
                ->waitFor($row)
                ->assertInputValue("{$row} input[name=\"group_name\"]", 'group2')
                ->assertInputValue("{$row} input[name=\"coverage_threshold\"]", '65')
                ->assertChecked("{$row} input[name=\"groupRadio\"]");
        });

        $group->refresh();
        self::assertSame('group2', $group->name);
        self::assertSame(65, $group->coveragethreshold);
        self::assertSame(1, $group->is_default);
    }

    public function testAssignsSubProjectToGroup(): void
    {
        $group1 = $this->project->subProjectGroups()->create(['name' => 'group1', 'is_default' => 1, 'position' => 1]);
        $group2 = $this->project->subProjectGroups()->create(['name' => 'group2', 'is_default' => 0, 'position' => 2]);
        $subproject = $this->project->subprojects()->create(['name' => 'aNewSubProject', 'groupid' => $group2->id]);

        $this->browse(function (Browser $browser) use ($subproject): void {
            $browser->loginAs($this->admin)
                ->visit("/manageSubProject.php?projectid={$this->project->id}")
                ->waitFor('@subproject-item .glyphicon-chevron-right')
                ->click('@subproject-item .glyphicon-chevron-right')
                ->waitFor('@subproject-item select[name="groupid"] option[label="group2"]:checked')
                ->click('@subproject-item select[name="groupid"] option[label="group1"]')
                ->waitFor("#group_changed_{$subproject->id}")

                ->refresh()
                ->waitFor('@subproject-item .glyphicon-chevron-right')
                ->click('@subproject-item .glyphicon-chevron-right')
                ->waitFor('@subproject-item select[name="groupid"] option[label="group1"]:checked');
        });

        self::assertSame($group1->id, $subproject->refresh()->groupid);
    }

    public function testFiltersSubProjectsByGroup(): void
    {
        $group1 = $this->project->subProjectGroups()->create(['name' => 'group1', 'is_default' => 1, 'position' => 1]);
        $group2 = $this->project->subProjectGroups()->create(['name' => 'group2', 'is_default' => 0, 'position' => 2]);
        $this->project->subprojects()->create(['name' => 'subproject1', 'groupid' => $group1->id]);
        $this->project->subprojects()->create(['name' => 'subproject2', 'groupid' => $group2->id]);

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->admin)
                ->visit("/manageSubProject.php?projectid={$this->project->id}")
                ->waitForText('subproject2')
                ->assertSee('subproject1')
                ->click('select[name="groupSelection"] option[label="group1"]')
                ->waitUntilMissingText('subproject2');

            self::assertCount(1, $browser->elements('@subproject-item'));
            $browser->assertSeeIn('@subproject-item', 'subproject1');
        });
    }

    public function testDeletesSubProjectGroups(): void
    {
        $group1 = $this->project->subProjectGroups()->create(['name' => 'group1', 'is_default' => 1, 'position' => 1]);
        $group2 = $this->project->subProjectGroups()->create(['name' => 'group2', 'is_default' => 0, 'position' => 2]);

        $this->browse(function (Browser $browser) use ($group1, $group2): void {
            $browser->loginAs($this->admin)
                ->visit("/manageSubProject.php?projectid={$this->project->id}")
                ->waitFor('@subproject-groups')
                ->click('@subproject-groups')

                // The default group can't be deleted until it's the only group left.
                ->waitFor("#sortable tr[id=\"{$group2->id}\"] .glyphicon-trash")
                ->assertMissing("#sortable tr[id=\"{$group1->id}\"] .glyphicon-trash")
                ->click("#sortable tr[id=\"{$group2->id}\"] .glyphicon-trash")
                ->waitUntilMissing("#sortable tr[id=\"{$group2->id}\"]")
                ->click("#sortable tr[id=\"{$group1->id}\"] .glyphicon-trash")
                ->waitUntilMissing('@existing-subproject-groups')

                ->refresh()
                ->waitFor('@subproject-groups')
                ->click('@subproject-groups')
                ->assertMissing('@existing-subproject-groups');
        });

        self::assertSame(0, $this->project->subProjectGroups()->count());
    }

    public function testChangesGroupOrder(): void
    {
        $this->project->subProjectGroups()->create(['name' => 'Development', 'is_default' => 1, 'position' => 1]);
        $this->project->subProjectGroups()->create(['name' => 'Third-party', 'is_default' => 0, 'position' => 2]);
        $production = $this->project->subProjectGroups()->create(['name' => 'Production', 'is_default' => 0, 'position' => 3]);

        $this->browse(function (Browser $browser) use ($production): void {
            $browser->loginAs($this->admin)
                ->visit("/manageSubProject.php?projectid={$this->project->id}")
                ->waitFor('@subproject-groups')
                ->click('@subproject-groups')
                ->waitFor("#sortable tr[id=\"{$production->id}\"]");

            // Drag the Production group to the top of the list.  jQuery UI only moves the row if the
            // pointer passes over the other rows on the way, so Dusk's single-step drag() doesn't work.
            $browser->driver->action()
                ->clickAndHold($browser->driver->findElement(WebDriverBy::cssSelector("#sortable tr[id=\"{$production->id}\"] td:nth-child(2)")))
                ->moveToElement($browser->driver->findElement(WebDriverBy::cssSelector('#sortable tr:nth-child(1)')))
                ->moveToElement($browser->driver->findElement(WebDriverBy::cssSelector('[data-test="existing-subproject-groups"] thead')))
                ->release()
                ->perform();

            $browser->assertInputValue('#sortable tr:nth-child(1) input[name="group_name"]', 'Production')
                ->press('Save Order')
                ->waitFor('#order_updated')

                ->refresh()
                ->waitFor('@subproject-groups')
                ->click('@subproject-groups')
                ->waitFor('#sortable tr:nth-child(3)')
                ->assertInputValue('#sortable tr:nth-child(1) input[name="group_name"]', 'Production')
                ->assertInputValue('#sortable tr:nth-child(2) input[name="group_name"]', 'Development')
                ->assertInputValue('#sortable tr:nth-child(3) input[name="group_name"]', 'Third-party');
        });

        self::assertSame(
            ['Production', 'Development', 'Third-party'],
            $this->project->subProjectGroups()->orderBy('position')->pluck('name')->all(),
        );
    }
}
