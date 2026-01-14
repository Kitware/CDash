<?php

namespace Tests\Browser\Pages;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\User;
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
    use UpdatesSiteInformation;

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
        $this->updateSiteInfoIfChanged($this->site, new SiteInformation([]));
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
                        ->waitFor('@no-active-projects-message')
                        ->assertVisible('@no-active-projects-message')
                        ->assertMissing('@projects-table')
                    ;
                });

            $build->submittime = Carbon::now();
            $build->save();

            $browser->visit('/projects')
                ->whenAvailable('@projects-page', function (Browser $browser): void {
                    $browser->click('@active-tab')
                        ->waitFor('@projects-table')
                        ->assertMissing('@no-active-projects-message')
                        ->assertSeeIn('@projects-table', $this->projects['project1']->name)
                    ;
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
            'submittime' => Carbon::now()->subWeek(),
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
}
