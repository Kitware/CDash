<?php

namespace Tests\Browser\Pages;

use App\Enums\BuildGroupType;
use App\Models\BuildGroup;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Facebook\WebDriver\WebDriverBy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

class ManageBuildGroupPageTest extends BrowserTestCase
{
    use CreatesProjects;

    private Project $project;
    private User $admin;
    private ?Project $otherProject = null;
    private ?Site $site = null;

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
            // Deleting a project deletes its build groups, their rules and its builds.
            $this->project->delete();
            $this->otherProject?->delete();
            $this->otherProject = null;
            $this->site?->delete();
            $this->site = null;
            $this->admin->delete();
        }

        parent::tearDown();
    }

    /**
     * Create a build group which is listed on the page after the three default groups.
     */
    private function createBuildGroup(string $name, BuildGroupType $type = BuildGroupType::DAILY): BuildGroup
    {
        $buildgroup = BuildGroup::factory()->for($this->project)->create([
            'name' => $name,
            'description' => '',
            'type' => $type,
        ]);
        $buildgroup->positions()->create([
            'position' => 4,
            'starttime' => Carbon::create(1980),
            'endtime' => Carbon::create(1980),
        ]);
        return $buildgroup;
    }

    public function testIsProtectedByLogin(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/manageBuildGroup.php?projectid={$this->project->id}")
                ->waitForTextIn('#subheadername', 'Login');
        });
    }

    public function testCreatesDailyAndLatestBuildGroups(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->admin)
                ->visit("/manageBuildGroup.php?projectid={$this->project->id}")
                ->waitForLink('Create new BuildGroup')
                ->clickLink('Create new BuildGroup')
                ->type('newBuildGroupName', 'aNewBuildGroup')
                ->assertPresent('select[name="newBuildGroupType"] option[label="Daily"]:checked')
                ->press('Create BuildGroup')
                ->waitFor('#buildgroup_created')
                ->type('newBuildGroupName', 'latestBuildGroup')
                ->click('select[name="newBuildGroupType"] option[label="Latest"]')
                ->press('Create BuildGroup')
                ->clickLink('Current BuildGroups')
                ->waitForTextIn('#current', 'latestBuildGroup')
                ->assertSeeIn('#current', 'aNewBuildGroup');
        });

        self::assertSame([
            'Nightly' => 'Daily',
            'Continuous' => 'Daily',
            'Experimental' => 'Daily',
            'aNewBuildGroup' => 'Daily',
            'latestBuildGroup' => 'Latest',
        ], $this->project->buildgroups()->orderBy('id')->get()->mapWithKeys(fn (BuildGroup $buildgroup) => [
            $buildgroup->name => $buildgroup->type->value,
        ])->all());
    }

    public function testCannotCreateBuildGroupWithEmptyName(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->admin)
                ->visit("/manageBuildGroup.php?projectid={$this->project->id}")
                ->waitForLink('Create new BuildGroup')
                ->clickLink('Create new BuildGroup')
                ->assertPresent('select[name="newBuildGroupType"] option[label="Daily"]:checked')
                ->press('Create BuildGroup')
                ->waitForTextIn('#create_group_error', 'Name of the BuildGroup must be a non-empty string.')
                ->assertMissing('#buildgroup_created')

                // A valid name clears the error.
                ->type('newBuildGroupName', 'a')
                ->press('Create BuildGroup')
                ->waitFor('#buildgroup_created')
                ->assertMissing('#create_group_error')

                ->type('newBuildGroupName', '        ')
                ->press('Create BuildGroup')
                ->waitForTextIn('#create_group_error', 'Name of the BuildGroup must be a non-empty string.');
        });

        self::assertSame(
            ['Nightly', 'Continuous', 'Experimental', 'a'],
            $this->project->buildgroups()->orderBy('id')->pluck('name')->all(),
        );
    }

    public function testCreatesBuildGroupAfterChoosingWildcardBuildType(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->admin)
                ->visit("/manageBuildGroup.php?projectid={$this->project->id}")
                ->waitForLink('Wildcard BuildGroups')

                // Choose a build type on the wildcard tab without submitting the form.
                ->clickLink('Wildcard BuildGroups')
                ->select('buildType', 'Experimental')

                ->clickLink('Create new BuildGroup')
                ->type('newBuildGroupName', 'anotherBuildGroup')
                ->assertPresent('select[name="newBuildGroupType"] option[label="Daily"]:checked')
                ->press('Create BuildGroup')
                ->waitFor('#buildgroup_created')
                ->clickLink('Current BuildGroups')
                ->assertSeeIn('#current', 'anotherBuildGroup');
        });

        $buildgroup = $this->project->buildgroups()->where('name', 'anotherBuildGroup')->firstOrFail();
        self::assertSame(BuildGroupType::DAILY, $buildgroup->type);
    }

    public function testPreventsDuplicateBuildGroups(): void
    {
        $this->createBuildGroup('aNewBuildGroup');

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->admin)
                ->visit("/manageBuildGroup.php?projectid={$this->project->id}")
                ->waitForLink('Create new BuildGroup')
                ->clickLink('Create new BuildGroup')
                ->type('newBuildGroupName', 'aNewBuildGroup')
                ->press('Create BuildGroup')
                ->waitForTextIn('#create_group_error', "A group named 'aNewBuildGroup' already exists for this project.");
        });

        self::assertSame(1, $this->project->buildgroups()->where('name', 'aNewBuildGroup')->count());
    }

    public function testNavigatesBetweenProjects(): void
    {
        $otherProject = $this->makePublicProject();
        $this->otherProject = $otherProject;

        $this->browse(function (Browser $browser) use ($otherProject): void {
            $browser->loginAs($this->admin)
                ->visit("/manageBuildGroup.php?projectid={$this->project->id}")
                ->waitFor("select[name=\"projectSelection\"] option[value=\"{$otherProject->id}\"]")
                ->waitForReload(fn (Browser $browser) => $browser->select('projectSelection', (string) $otherProject->id))
                ->assertPathIs('/manageBuildGroup.php')
                ->assertQueryStringHas('projectid', (string) $otherProject->id)
                ->waitFor("select[name=\"projectSelection\"] option[value=\"{$this->project->id}\"]")
                ->waitForReload(fn (Browser $browser) => $browser->select('projectSelection', (string) $this->project->id))
                ->assertPathIs('/manageBuildGroup.php')
                ->assertQueryStringHas('projectid', (string) $this->project->id);
        });
    }

    public function testModifiesBuildGroup(): void
    {
        $buildgroup = $this->createBuildGroup('aNewBuildGroup');
        $row = "#sortable [id=\"{$buildgroup->id}\"]";

        $this->browse(function (Browser $browser) use ($buildgroup, $row): void {
            $browser->loginAs($this->admin)
                ->visit("/manageBuildGroup.php?projectid={$this->project->id}")
                ->waitFor("{$row} .glyphicon-chevron-right")
                ->click("{$row} .glyphicon-chevron-right")
                ->assertInputValue("{$row} input[name=\"name\"]", 'aNewBuildGroup')
                ->assertInputValue("{$row} input[name=\"description\"]", '')
                ->type("{$row} input[name=\"description\"]", 'temporary BuildGroup for testing')
                ->assertInputValue("{$row} input[name=\"autoremovetimeframe\"]", '0')
                ->type("{$row} input[name=\"autoremovetimeframe\"]", '1')
                ->assertChecked("{$row} input[name=\"summaryEmail\"][value=\"0\"]")
                ->assertNotChecked("{$row} input[name=\"summaryEmail\"][value=\"1\"]")
                ->assertNotChecked("{$row} input[name=\"summaryEmail\"][value=\"2\"]")
                ->click("{$row} input[name=\"summaryEmail\"][value=\"2\"]")
                ->assertNotChecked("{$row} input[name=\"emailCommitters\"]")
                ->check("{$row} input[name=\"emailCommitters\"]")
                ->assertChecked("{$row} input[name=\"includeInSummary\"]")
                ->uncheck("{$row} input[name=\"includeInSummary\"]")
                ->click("{$row} button[type=\"submit\"]")
                ->waitFor("#buildgroup_updated_{$buildgroup->id}")

                ->refresh()
                ->waitFor("{$row} .glyphicon-chevron-right")
                ->click("{$row} .glyphicon-chevron-right")
                ->assertInputValue("{$row} input[name=\"name\"]", 'aNewBuildGroup')
                ->assertInputValue("{$row} input[name=\"description\"]", 'temporary BuildGroup for testing')
                ->assertInputValue("{$row} input[name=\"autoremovetimeframe\"]", '1')
                ->assertNotChecked("{$row} input[name=\"summaryEmail\"][value=\"0\"]")
                ->assertNotChecked("{$row} input[name=\"summaryEmail\"][value=\"1\"]")
                ->assertChecked("{$row} input[name=\"summaryEmail\"][value=\"2\"]")
                ->assertChecked("{$row} input[name=\"emailCommitters\"]")
                ->assertNotChecked("{$row} input[name=\"includeInSummary\"]");
        });
    }

    public function testCreatesAndDeletesWildcardRules(): void
    {
        $buildgroup = $this->createBuildGroup('aNewBuildGroup');

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->admin)
                ->visit("/manageBuildGroup.php?projectid={$this->project->id}")
                ->waitForLink('Wildcard BuildGroups')
                ->clickLink('Wildcard BuildGroups')
                ->click('select[name="wildcardBuildGroupSelection"] option[label="aNewBuildGroup"]')
                ->assertInputValue('wildcardBuildNameMatch', '')
                ->type('wildcardBuildNameMatch', 'simple')
                ->assertSelected('buildType', 'Nightly')
                ->select('buildType', 'Experimental')
                ->press('Define BuildGroup')
                ->waitFor('#wildcard_defined')

                ->refresh()
                ->waitForLink('Wildcard BuildGroups')
                ->clickLink('Wildcard BuildGroups')
                ->waitFor('[name="existingwildcardrules"]')
                ->assertSeeIn('[name="existingwildcardrules"] tbody tr:nth-child(1) td:nth-child(1)', 'aNewBuildGroup')
                ->assertSeeIn('[name="existingwildcardrules"] tbody tr:nth-child(1) td:nth-child(2)', 'simple')
                ->assertSeeIn('[name="existingwildcardrules"] tbody tr:nth-child(1) td:nth-child(3)', 'Experimental')
                ->click('[name="existingwildcardrules"] tbody tr:nth-child(1) .glyphicon-trash')
                ->waitUntilMissing('[name="existingwildcardrules"]')

                ->refresh()
                ->waitForLink('Wildcard BuildGroups')
                ->clickLink('Wildcard BuildGroups')
                ->assertMissing('[name="existingwildcardrules"]');
        });

        self::assertSame(0, $buildgroup->rules()->active()->count());
    }

    public function testDefinesAndDeletesDynamicRows(): void
    {
        $buildgroup = $this->createBuildGroup('latestBuildGroup', BuildGroupType::LATEST);

        // The site menu only lists sites which recently submitted to the project.
        $site = Site::factory()->create();
        $this->site = $site;
        $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
            'siteid' => $site->id,
            'starttime' => Carbon::now()->subHour(),
        ]);

        $this->browse(function (Browser $browser) use ($site): void {
            $browser->loginAs($this->admin)
                ->visit("/manageBuildGroup.php?projectid={$this->project->id}")
                ->waitForLink('Dynamic BuildGroups')
                ->clickLink('Dynamic BuildGroups')
                ->click('select[name="dynamicSelection"] option[label="latestBuildGroup"]')

                ->click('select[name="parentBuildGroupSelection"] option[label="Experimental"]')
                ->click("select[name=\"siteSelection\"] option[label=\"{$site->name}\"]")
                ->assertInputValue('dynamicBuildNameMatch', '')
                ->type('dynamicBuildNameMatch', 'CDash-CTest-sameImage')
                ->press('Add content to BuildGroup')
                ->waitFor('[name="existingdynamicrows"] tbody tr:nth-child(1)')

                ->click('select[name="parentBuildGroupSelection"] option[label="Continuous"]')
                ->select('siteSelection', '')
                ->keys('input[name="dynamicBuildNameMatch"]', ['{control}', 'a'], '{backspace}')
                ->press('Add content to BuildGroup')
                ->waitFor('[name="existingdynamicrows"] tbody tr:nth-child(2)')

                ->refresh()
                ->waitForLink('Dynamic BuildGroups')
                ->clickLink('Dynamic BuildGroups')
                ->click('select[name="dynamicSelection"] option[label="latestBuildGroup"]')
                ->waitFor('[name="existingdynamicrows"]');

            // The rules aren't sorted, so they can be listed in either order.
            self::assertSame([
                ['Continuous', 'Any', ''],
                ['Experimental', $site->name, 'CDash-CTest-sameImage'],
            ], collect($browser->elements('[name="existingdynamicrows"] tbody tr'))->map(fn ($row) => [
                $row->findElement(WebDriverBy::cssSelector('td:nth-child(1)'))->getText(),
                $row->findElement(WebDriverBy::cssSelector('td:nth-child(2)'))->getText(),
                $row->findElement(WebDriverBy::cssSelector('td:nth-child(3)'))->getText(),
            ])->sort()->values()->all());

            $browser->click('[name="existingdynamicrows"] tbody tr:nth-child(2) .glyphicon-trash')
                ->waitUntilMissing('[name="existingdynamicrows"] tbody tr:nth-child(2)')
                ->click('[name="existingdynamicrows"] tbody tr:nth-child(1) .glyphicon-trash')
                ->waitUntilMissing('[name="existingdynamicrows"]')

                ->refresh()
                ->waitForLink('Dynamic BuildGroups')
                ->clickLink('Dynamic BuildGroups')
                ->click('select[name="dynamicSelection"] option[label="latestBuildGroup"]')
                ->assertMissing('[name="existingdynamicrows"]');
        });

        self::assertSame(0, $buildgroup->rules()->active()->count());
    }

    public function testDeletesBuildGroups(): void
    {
        $buildgroups = [
            $this->createBuildGroup('aNewBuildGroup'),
            $this->createBuildGroup('latestBuildGroup', BuildGroupType::LATEST),
        ];

        $this->browse(function (Browser $browser) use ($buildgroups): void {
            $browser->loginAs($this->admin)
                ->visit("/manageBuildGroup.php?projectid={$this->project->id}");

            foreach ($buildgroups as $buildgroup) {
                $row = "#sortable [id=\"{$buildgroup->id}\"]";
                $browser->waitFor("{$row} .glyphicon-chevron-right")
                    ->click("{$row} .glyphicon-chevron-right")
                    ->click("{$row} .glyphicon-trash")
                    ->waitFor('#modal-delete-group-button')
                    ->click('#modal-delete-group-button')
                    ->waitUntilMissing($row)

                    // Make sure it's really gone from the database too.
                    ->refresh()
                    ->waitForTextIn('#sortable', 'Experimental')
                    ->assertMissing($row);
            }
        });

        self::assertSame(
            ['Nightly', 'Continuous', 'Experimental'],
            $this->project->buildgroups()->active()->orderBy('id')->pluck('name')->all(),
        );
    }
}
