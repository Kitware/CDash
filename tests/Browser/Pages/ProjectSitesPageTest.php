<?php

namespace Tests\Browser\Pages;

use App\Models\Project;
use App\Models\Site;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class ProjectSitesPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;

    /**
     * @var array<Project>
     */
    private array $projects = [];

    /**
     * @var array<Site>
     */
    private array $sites = [];

    public function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->projects as $project) {
            $project->delete();
        }
        $this->projects = [];

        foreach ($this->sites as $site) {
            $site->delete();
        }
        $this->sites = [];
    }

    public function testSiteDisplaysLatestInformation(): void
    {
        $project = $this->makePublicProject();
        $this->projects[] = $project;
        $site = $this->makeSite();
        $this->sites[] = $site;
        $site->information()->createMany([
            [
                'totalphysicalmemory' => 5678,
                'numberphysicalcpus' => 2,
            ],
            [
                'totalphysicalmemory' => 8765,
                'numberphysicalcpus' => 4,
            ],
        ]);
        $project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
            'siteid' => $site->id,
        ]);

        $this->browse(function (Browser $browser) use ($project) {
            $browser->visit("/projects/{$project->id}/sites")
                ->whenAvailable('@all-sites-table > @data-table', function (Browser $browser) {
                    self::assertEquals('4', $browser->elements('@data-table-cell')[1]->getText());
                    self::assertEquals('8.56 GiB', $browser->elements('@data-table-cell')[2]->getText());
                });
        });
    }

    public function testSiteListPagination(): void
    {
        $project = $this->makePublicProject();
        $this->projects[] = $project;
        $sites = [];
        for ($i = 0; $i < 120; $i++) {
            $sites[$i] = $this->makeSite();
        }
        $this->sites = $sites;

        // No submissions to the project yet, so we shouldn't see any sites
        $this->browse(function (Browser $browser) use ($project) {
            $browser->visit("/projects/{$project->id}/sites")
                ->whenAvailable('@all-sites-table > @data-table', function (Browser $browser) {
                    self::assertCount(0, collect($browser->elements('@data-table-row')));
                });
        });

        // Submit a build for the first 110 sites so we have enough data to verify that pagination works
        for ($i = 0; $i < 110; $i++) {
            $project->builds()->create([
                'name' => 'build1',
                'uuid' => Str::uuid()->toString(),
                'siteid' => $sites[$i]->id,
            ]);
        }

        // Submit a second build for a few sites to verify that the sites displayed actually correspond to unique sites
        for ($i = 0; $i < 5; $i++) {
            $project->builds()->create([
                'name' => 'build1',
                'uuid' => Str::uuid()->toString(),
                'siteid' => $sites[$i]->id,
            ]);
        }

        $this->browse(function (Browser $browser) use ($sites, $project) {
            $browser->visit("/projects/{$project->id}/sites")
                ->whenAvailable('@all-sites-table > @data-table', function (Browser $browser) use ($sites) {
                    // We have to add a brief pause to let Vue render both parts of the table
                    // This should be replaced with a more robust solution in the future.
                    $browser->pause(500);

                    self::assertCount(110, collect($browser->elements('@data-table-row')));

                    for ($i = 0; $i < 110; $i++) {
                        $browser->assertSee($sites[$i]->name);
                    }
                });
        });
    }
}
