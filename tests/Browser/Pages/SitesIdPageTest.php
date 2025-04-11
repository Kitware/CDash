<?php

namespace Tests\Browser\Pages;

use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;
use Tests\Traits\CreatesUsers;

class SitesIdPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use CreatesUsers;

    /**
     * @var array<Project>
     */
    private array $projects = [];

    /**
     * @var array<Site>
     */
    private array $sites = [];

    /**
     * @var array<User>
     */
    private array $users = [];

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

        foreach ($this->users as $users) {
            $users->delete();
        }
        $this->users = [];
    }

    public function testMostRecentSiteDetails(): void
    {
        $this->sites['site1'] = $this->makeSite();
        $this->sites['site1']->information()->createMany([
            [
                'totalphysicalmemory' => 5678,
                'numberphysicalcpus' => 2,
            ],
            [
                'totalphysicalmemory' => 8765,
                'numberphysicalcpus' => 4,
            ],
        ]);

        $this->projects['public'] = $this->makePublicProject();
        $this->projects['public']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit("/sites/{$this->sites['site1']->id}")
                ->whenAvailable('@site-details @site-details-table', function (Browser $browser) {
                    // Just spot check a couple fields
                    self::assertEquals('4', $browser->elements('@site-details-table-cell')[7]->getText());
                    self::assertEquals('8.56 GiB', $browser->elements('@site-details-table-cell')[9]->getText());
                })
                ->whenAvailable('@site-details @site-description', function (Browser $browser) {
                    $browser->assertSee('No description provided...');
                });
        });

        $this->sites['site1']->mostRecentInformation->description = 'abc';
        $this->sites['site1']->mostRecentInformation?->save();

        $this->browse(function (Browser $browser) {
            $browser->visit("/sites/{$this->sites['site1']->id}")
                ->whenAvailable('@site-details @site-description', function (Browser $browser) {
                    $browser->assertSee('abc');
                });
        });
    }

    public function testProjectsList(): void
    {
        $this->sites['site1'] = $this->makeSite();

        $this->projects['public1'] = $this->makePublicProject();
        $this->projects['public1']->description = Str::uuid()->toString();
        $this->projects['public1']->save();
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->projects['public2'] = $this->makePublicProject();
        $this->projects['public2']->description = Str::uuid()->toString();
        $this->projects['public2']->save();
        $this->projects['public2']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit("/sites/{$this->sites['site1']->id}")
                ->whenAvailable('@site-projects-table', function (Browser $browser) {
                    $browser->assertSee($this->projects['public1']->name);
                    $browser->assertSee($this->projects['public2']->name);
                    $browser->assertSee($this->projects['public1']->description);
                    $browser->assertSee($this->projects['public2']->description);
                });
        });
    }

    public function testHistoryList(): void
    {
        $this->sites['site1'] = $this->makeSite();
        $this->sites['site1']->information()->forceCreate([
            'timestamp' => Carbon::now()->subMinutes(5),
            'totalphysicalmemory' => 5678,
            'numberphysicalcpus' => 2,
        ]);
        $this->sites['site1']->information()->forceCreate([
            'timestamp' => Carbon::now()->subMinutes(4),
            'totalphysicalmemory' => 8765,
            'numberphysicalcpus' => 4,
        ]);
        $this->sites['site1']->information()->forceCreate([
            'timestamp' => Carbon::now()->subMinutes(3),
            'totalphysicalmemory' => 8765,
            'numberphysicalcpus' => 4,
            'description' => 'description1',
        ]);
        $this->sites['site1']->information()->forceCreate([
            'timestamp' => Carbon::now()->subMinutes(2),
            'totalphysicalmemory' => 8765,
            'numberphysicalcpus' => 4,
            'description' => 'description2',
        ]);
        $this->sites['site1']->information()->forceCreate([
            'timestamp' => Carbon::now(),
            'totalphysicalmemory' => 10765,
            'numberphysicalcpus' => 8,
            'description' => 'description2',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit("/sites/{$this->sites['site1']->id}")
                ->whenAvailable('@site-history', function (Browser $browser) {
                    $browser->waitFor('@site-history-item');
                    // Can't use nth child with @ selector unfortunately
                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(1)', 'System Update');
                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(1)', '10.51 GiB');
                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(1)', '8');

                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(2)', 'Description Changed');
                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(2) [data-test="old-description"]', 'description1');
                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(2) [data-test="new-description"]', 'description2');

                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(3)', 'Description Changed');
                    $browser->assertMissing('[data-test="site-history-item"]:nth-child(3) [data-test="old-description"]');
                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(3) [data-test="new-description"]', 'description1');

                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(4)', 'System Update');
                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(4)', '8.56 GiB');
                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(4)', '4');

                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(5)', 'Site Created');
                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(5)', '5.54 GiB');
                    $browser->assertSeeIn('[data-test="site-history-item"]:nth-child(5)', '2');
                });
        });
    }

    /**
     * Deduplication is needed until duplicate site information is resolved.
     */
    public function testHistoryDeduplication(): void
    {
        $this->sites['site1'] = $this->makeSite();
        $this->sites['site1']->information()->forceCreate([
            'timestamp' => Carbon::now()->subMinutes(5),
            'totalphysicalmemory' => 5678,
            'numberphysicalcpus' => 2,
        ]);
        $this->sites['site1']->information()->forceCreate([
            'timestamp' => Carbon::now()->subMinutes(4),
            'totalphysicalmemory' => 8765,
            'numberphysicalcpus' => 4,
        ]);
        $this->sites['site1']->information()->forceCreate([
            'timestamp' => Carbon::now()->subMinutes(3),
            'totalphysicalmemory' => 8765,
            'numberphysicalcpus' => 4,
        ]);
        $this->sites['site1']->information()->forceCreate([
            'timestamp' => Carbon::now()->subMinutes(2),
            'totalphysicalmemory' => 8765,
            'numberphysicalcpus' => 6,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->visit("/sites/{$this->sites['site1']->id}")
                ->whenAvailable('@site-history', function (Browser $browser) {
                    // Can't use nth child with @ selector unfortunately
                    $browser->whenAvailable('[data-test="site-history-item"]:nth-child(1)', function (Browser $browser) {
                        $browser->assertSee('System Update');
                        $browser->assertSee('8.56 GiB');
                        $browser->assertSee('6');
                    });

                    $browser->whenAvailable('[data-test="site-history-item"]:nth-child(2)', function (Browser $browser) {
                        $browser->assertSee('System Update');
                        $browser->assertSee('8.56 GiB');
                        $browser->assertSee('4');
                    });

                    $browser->whenAvailable('[data-test="site-history-item"]:nth-child(3)', function (Browser $browser) {
                        $browser->assertSee('Site Created');
                        $browser->assertSee('5.54 GiB');
                        $browser->assertSee('2');
                    });
                });
        });
    }

    public function testSiteWithNoInformation(): void
    {
        $this->sites['site1'] = $this->makeSite();

        $this->browse(function (Browser $browser) {
            $browser->visit("/sites/{$this->sites['site1']->id}")
                ->whenAvailable('@site-details', function (Browser $browser) {
                    $browser->waitForText('No information available for this site.')
                        ->assertSee('No information available for this site.');
                });
        });
    }

    public function testClaimSiteFunctionality(): void
    {
        $this->sites['site1'] = $this->makeSite();
        $this->users['user'] = $this->makeNormalUser();

        $this->browse(function (Browser $browser) {
            // We shouldn't see the claim/unclaim site button when we're logged out
            $browser->visit("/sites/{$this->sites['site1']->id}")
                ->assertMissing('@claim-site-button')
                ->assertMissing('@unclaim-site-button')
                ->whenAvailable('@site-maintainers-table', function (Browser $browser) {
                    $browser->assertDontSee($this->users['user']->firstname);
                });

            $browser->loginAs($this->users['user'])
                // Users who haven't claimed the site should be able to claim the site
                ->visit("/sites/{$this->sites['site1']->id}")
                ->waitFor('@claim-site-button')
                ->assertVisible('@claim-site-button')
                ->assertMissing('@unclaim-site-button')
                ->whenAvailable('@site-maintainers-table', function (Browser $browser) {
                    $browser->assertDontSee($this->users['user']->firstname);
                })
                // Claim the site and ensure that it shows up as expected
                ->click('@claim-site-button')
                ->waitFor('@unclaim-site-button')
                ->assertVisible('@unclaim-site-button')
                ->assertMissing('@claim-site-button')
                ->waitForTextIn('@site-maintainers-table', "{$this->users['user']->firstname} {$this->users['user']->lastname} ({$this->users['user']->institution})")
                // Unclaim the site and ensure that it disappears
                ->click('@unclaim-site-button')
                ->waitFor('@claim-site-button')
                ->assertVisible('@claim-site-button')
                ->assertMissing('@unclaim-site-button')
                ->waitUntilMissingText("{$this->users['user']->firstname} {$this->users['user']->lastname} ({$this->users['user']->institution})");
        });
    }

    public function testEditSiteDescription(): void
    {
        $this->sites['site1'] = $this->makeSite();
        $this->sites['site1']->information()->create();
        $this->users['user'] = $this->makeNormalUser();

        $this->browse(function (Browser $browser) {
            // We shouldn't see buttons to edit the description if we are logged out
            $browser->visit("/sites/{$this->sites['site1']->id}")
                ->whenAvailable('@site-description', function (Browser $browser) {
                    $browser->assertMissing('@cancel-edit-description-button');
                    $browser->assertMissing('@save-description-button');
                    $browser->assertMissing('@edit-description-button');
                });

            $description1 = Str::uuid()->toString();
            $description2 = Str::uuid()->toString();

            $browser->loginAs($this->users['user'])
                ->visit("/sites/{$this->sites['site1']->id}")
                ->waitFor('@site-description')
                ->assertMissing('@cancel-edit-description-button')
                ->assertMissing('@save-description-button')
                ->assertVisible('@edit-description-button')
                ->waitForTextIn('@site-description', 'No description provided')
                ->click('@edit-description-button')
                ->waitFor('@cancel-edit-description-button')
                ->assertVisible('@cancel-edit-description-button')
                ->assertVisible('@save-description-button')
                ->assertMissing('@edit-description-button')
                ->assertVisible('@edit-description-textarea')
                ->assertValue('@edit-description-textarea', '')
                ->type('@edit-description-textarea', $description1)
                ->click('@cancel-edit-description-button')
                ->waitForTextIn('@site-description', 'No description provided')
                ->assertDontSee($description1)
                ->assertMissing('@cancel-edit-description-button')
                ->assertMissing('@save-description-button')
                ->assertVisible('@edit-description-button')
                ->click('@edit-description-button')
                ->assertVisible('@cancel-edit-description-button')
                ->assertVisible('@save-description-button')
                ->assertMissing('@edit-description-button')
                ->assertVisible('@edit-description-textarea')
                ->assertValue('@edit-description-textarea', '')
                ->type('@edit-description-textarea', $description1)
                ->click('@save-description-button')
                ->waitFor('@edit-description-button')
                ->assertMissing('@cancel-edit-description-button')
                ->assertMissing('@save-description-button')
                ->assertVisible('@edit-description-button')
                ->assertSeeIn('@site-description', $description1)
                ->assertSeeIn('[data-test="site-history-item"]:nth-child(1)', $description1)
                ->refresh()
                ->waitFor('@site-description')
                ->assertMissing('@cancel-edit-description-button')
                ->assertMissing('@save-description-button')
                ->assertVisible('@edit-description-button')
                ->waitForTextIn('@site-description', $description1)
                ->waitForTextIn('[data-test="site-history-item"]:nth-child(1)', $description1)
                ->click('@edit-description-button')
                ->assertValue('@edit-description-textarea', $description1)
                ->clear('@edit-description-textarea')
                ->type('@edit-description-textarea', $description2)
                ->click('@save-description-button')
                ->waitForTextIn('@site-description', $description2)
                ->waitForTextIn('[data-test="site-history-item"]:nth-child(1)', $description2)
                ->waitForTextIn('[data-test="site-history-item"]:nth-child(2)', $description1)
                ->refresh()
                ->waitFor('@site-description')
                ->waitForTextIn('@site-description', $description2)
                ->waitForTextIn('[data-test="site-history-item"]:nth-child(1)', $description2)
                ->waitForTextIn('[data-test="site-history-item"]:nth-child(2)', $description1)
            ;
        });
    }
}
