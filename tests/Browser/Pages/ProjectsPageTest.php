<?php

namespace Tests\Browser\Pages;

use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\User;
use App\Services\SiteService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;
use Tests\Traits\CreatesUsers;

class ProjectsPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use CreatesUsers;
    use DatabaseTruncation;

    /**
     * @var array<User>
     */
    private array $users = [];

    /**
     * @var array<Project>
     */
    private array $projects = [];

    private Site $site;

    public function setUp(): void
    {
        parent::setUp();

        $this->site = $this->makeSite();
        SiteService::updateSiteInfoIfChanged($this->site, new SiteInformation([]));
    }

    public function tearDown(): void
    {
        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->users = [];

        foreach ($this->projects as $project) {
            $project->delete();
        }
        $this->projects = [];

        $this->site->delete();

        parent::tearDown();
    }

    public function testCreateProjectButtonOnlyVisibleToAdminsByDefault(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $this->users['normal'] = $this->makeNormalUser();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->assertVisible('@create-project-button');
                });
            $browser->loginAs($this->users['normal'])
                ->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->assertMissing('@create-project-button');
                });

            $browser
                ->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->assertMissing('@create-project-button');
                });
        });
    }

    public function testShowsMessageWhenNoProjects(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->click('@all-tab')
                        ->waitFor('@no-projects-message')
                        ->assertVisible('@no-projects-message')
                        ->assertSee('No projects to display')
                        ->assertMissing('@projects-table')
                    ;
                });

            $this->projects['project1'] = $this->makePublicProject();

            $browser->loginAs($this->users['admin'])
                ->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->click('@all-tab')
                        ->waitFor('@projects-table')
                        ->assertMissing('@no-projects-message')
                        ->assertDontSee('No projects to display')
                        ->assertSeeIn('@projects-table', $this->projects['project1']->name)
                    ;
                });
        });
    }

    public function testShowsMessageWhenNoActiveProjects(): void
    {
        $this->projects['project1'] = $this->makePublicProject();
        $build = $this->projects['project1']->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'submittime' => Carbon::now()->subDays(2),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->click('@active-tab')
                        ->waitFor('@no-projects-message')
                        ->assertSee('No projects with builds in the last 24 hours')
                        ->assertMissing('@projects-table')
                    ;
                });

            $build->submittime = Carbon::now();
            $build->save();

            $browser->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->click('@active-tab')
                        ->waitFor('@projects-table')
                        ->assertMissing('@no-projects-message')
                        ->assertDontSee('No projects with builds in the last 24 hours')
                        ->assertSeeIn('@projects-table', $this->projects['project1']->name)
                    ;
                });
        });
    }

    public function testShowsMessageWhenNoMemberProjects(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $this->projects['project1'] = $this->makePublicProject();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->click('@member-tab')
                        ->waitFor('@no-projects-message')
                        ->assertVisible('@no-projects-message')
                        ->assertSee('You are not a member of any projects yet')
                        ->assertMissing('@projects-table')
                    ;
                });

            $this->projects['project1']->users()->attach($this->users['admin'], ['role' => Project::PROJECT_USER]);

            $browser->loginAs($this->users['admin'])
                ->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->click('@member-tab')
                        ->waitFor('@projects-table')
                        ->assertMissing('@no-projects-message')
                        ->assertDontSee('You are not a member of any projects yet')
                        ->assertSeeIn('@projects-table', $this->projects['project1']->name)
                    ;
                });
        });
    }

    public function testOnlyShowsMemberTabWhenLoggedIn(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $this->projects['project1'] = $this->makePublicProject();

        $this->browse(function (Browser $browser): void {
            $browser->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->assertMissing('@member-tab');
                });

            $browser->loginAs($this->users['admin'])
                ->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->assertVisible('@member-tab');
                });
        });
    }

    public function testSortsProjectsByNumberOfBuildsInLastDay(): void
    {
        $this->projects['project1'] = $this->makePublicProject();
        $this->projects['project1']->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'submittime' => Carbon::now()->subWeek(),
        ]);
        $this->projects['project1']->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'submittime' => Carbon::now(),
        ]);

        $this->projects['project2'] = $this->makePublicProject();
        $this->projects['project2']->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'submittime' => Carbon::now(),
        ]);
        $this->projects['project2']->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'submittime' => Carbon::now()->subHour(),
        ]);
        $this->projects['project2']->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'submittime' => Carbon::now()->subHours(2),
        ]);

        $this->projects['project3'] = $this->makePublicProject();
        $this->projects['project3']->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'submittime' => Carbon::now(),
        ]);
        $this->projects['project3']->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'submittime' => Carbon::now()->subHour(),
        ]);

        $this->browse(function (Browser $browser): void {
            $browser->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->click('@all-tab')
                        ->waitFor('@projects-table')
                    ;

                    self::assertSame($this->projects['project2']->name, $browser->elements('@project-name')[0]->getText());
                    self::assertSame($this->projects['project3']->name, $browser->elements('@project-name')[1]->getText());
                    self::assertSame($this->projects['project1']->name, $browser->elements('@project-name')[2]->getText());
                });
        });
    }

    public function testShowsDateOfLastSubmission(): void
    {
        $this->projects['project1'] = $this->makePublicProject();
        $this->projects['project1']->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'submittime' => Carbon::now()->subWeek()->subHour(),
        ]);

        $this->browse(function (Browser $browser): void {
            $browser->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->click('@all-tab')
                        ->waitFor('@projects-table')
                        ->assertSeeIn('@projects-table', '7 days ago')
                    ;
                });
        });
    }

    public function testPaginationLoadsAllProjects(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        // Create 110 public projects, each with a recent build so they appear on the active tab
        for ($i = 1; $i <= 110; $i++) {
            $project = $this->makePublicProject();
            $this->projects["project{$i}"] = $project;

            $project->builds()->create([
                'siteid' => $this->site->id,
                'name' => Str::uuid()->toString(),
                'uuid' => Str::uuid()->toString(),
                'submittime' => Carbon::now(),
            ]);

            $project->users()->attach($this->users['admin'], ['role' => Project::PROJECT_USER]);
        }

        // The first project created has the lowest ID (first pagination page) and the last has
        // the highest ID (final pagination page).  Waiting for the last project's name guarantees
        // all pages have been fetched before asserting.
        $firstProjectName = $this->projects['project1']->name;
        $lastProjectName = $this->projects['project110']->name;

        $this->browse(function (Browser $browser) use ($firstProjectName, $lastProjectName): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser) use ($firstProjectName, $lastProjectName): void {
                    // Test "All" tab shows projects with lowest and highest IDs
                    $browser->click('@all-tab')
                        ->waitFor('@projects-table')
                        ->waitForText($lastProjectName)
                        ->assertSeeIn('@projects-table', $firstProjectName)
                        ->assertSeeIn('@projects-table', $lastProjectName);

                    // Test "Active" tab shows projects with lowest and highest IDs
                    $browser->click('@active-tab')
                        ->waitFor('@projects-table')
                        ->waitForText($lastProjectName)
                        ->assertSeeIn('@projects-table', $firstProjectName)
                        ->assertSeeIn('@projects-table', $lastProjectName);

                    // Test "Member" tab shows projects with lowest and highest IDs
                    $browser->click('@member-tab')
                        ->waitFor('@projects-table')
                        ->waitForText($lastProjectName)
                        ->assertSeeIn('@projects-table', $firstProjectName)
                        ->assertSeeIn('@projects-table', $lastProjectName);
                });
        });
    }
}
