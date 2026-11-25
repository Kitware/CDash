<?php

namespace Tests\Browser\Pages;

use App\Models\Build;
use App\Models\Configure;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\SubProject;
use App\Services\SiteService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

class BuildSummaryPageTest extends BrowserTestCase
{
    use CreatesProjects;

    private Project $project;
    private Site $site;

    /**
     * Records which aren't deleted along with the project.
     *
     * @var array<Site>
     */
    private array $cleanup = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->site = Site::factory()->create();
        SiteService::updateSiteInfoIfChanged($this->site, new SiteInformation([]));
    }

    public function tearDown(): void
    {
        $this->project->delete();
        foreach ($this->cleanup as $model) {
            $model->delete();
        }
        $this->cleanup = [];
        $this->site->delete();

        parent::tearDown();
    }

    public function testShowsBuildName(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}")
                ->waitForText($build->name)
                ->assertSee($build->name)
            ;
        });
    }

    public function testShowsPrefilledGitLabIssueForFailedBuild(): void
    {
        $url = 'https://gitlab.example.org/group/project/-/issues/new?issuable_template=Build';
        $this->project->update(['bugtrackertype' => 'GitLab', 'bugtrackernewissueurl' => $url]);
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => 'Linux C++ & tests',
            'uuid' => Str::uuid()->toString(),
            'type' => 'Experimental',
            'builderrors' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($build, $url): void {
            $browser->visit("/builds/{$build->id}")
                ->waitFor('@new-issue-link')
                ->assertSeeLink('Create GitLab issue for this build')
            ;
            $link = $browser->attribute('@new-issue-link', 'href');
            self::assertIsString($link);
            self::assertStringStartsWith($url . '&', $link);
            parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
            self::assertIsArray($query['issue']);
            self::assertSame("FAILED (b=1): {$this->project->name} - {$build->name} - Experimental", $query['issue']['title']);
            self::assertIsString($query['issue']['description']);
            self::assertStringContainsString("/builds/{$build->id}", $query['issue']['description']);
        });
    }

    public function testShowsHistoryLink(): void
    {
        $buildName = 'Linux-g++_' . Str::uuid()->toString();
        $buildType = 'Nightly';
        $startTime = '2024-03-15T08:30:00+00:00';

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => $buildName,
            'type' => $buildType,
            'starttime' => $startTime,
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build, $buildName, $buildType): void {
            $browser->visit("/builds/{$build->id}")
                ->waitFor('[data-test="build-history-link"][href]')
                ->assertAttributeContains('@build-history-link', 'href', 'project=' . urlencode($this->project->name))
                ->assertAttributeContains('@build-history-link', 'href', 'value1=' . urlencode($this->site->name))
                ->assertAttributeContains('@build-history-link', 'href', 'value2=' . rawurlencode($buildName))
                ->assertAttributeContains('@build-history-link', 'href', 'value3=' . rawurlencode($buildType))
                // Builds which started between the oldest build in the chart (this one) and this build.
                ->assertAttributeContains('@build-history-link', 'href', 'field4=buildstarttime&compare4=83&value4=' . rawurlencode('2024-03-15T08:29:59Z'))
                ->assertAttributeContains('@build-history-link', 'href', 'field5=buildstarttime&compare5=84&value5=' . rawurlencode('2024-03-15T08:30:01Z'))
            ;
        });
    }

    public function testBuildTimelineShowsAllStages(): void
    {
        $startTime = Carbon::now()->subHours(2);
        $endTime = Carbon::now()->subHour();
        $submitTime = Carbon::now()->subMinutes(30);

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'starttime' => $startTime->toIso8601String(),
            'endtime' => $endTime->toIso8601String(),
            'submittime' => $submitTime->toIso8601String(),
            'configureerrors' => 0,
            'configurewarnings' => 0,
            'builderrors' => 0,
            'buildwarnings' => 0,
            'testpassed' => 10,
            'testfailed' => 0,
            'testnotrun' => 0,
            'configureduration' => 60,
            'buildduration' => 300,
            'testduration' => 120,
        ]);

        $build->configure()->create(Configure::factory()->make()->toArray());

        $this->browse(function (Browser $browser) use ($submitTime, $endTime, $startTime, $build): void {
            $browser->resize(1920, 1080)
                ->visit("/builds/{$build->id}")
                ->waitFor('@build-timeline')
                ->within('@build-timeline', function (Browser $timeline) use ($submitTime, $endTime, $startTime): void {
                    $timeline->waitForText('Start')
                        ->waitForText('Configure')
                        ->waitForText('Build')
                        ->waitForText('Test')
                        ->waitForText('End')
                        ->waitForText('Submit')
                        ->assertSee('Start')
                        ->assertSee('Configure')
                        ->assertSee('Build')
                        ->assertSee('Test')
                        ->assertSee('End')
                        ->assertSee('Submit')
                        ->assertSee('1 min') // Configure 60s
                        ->assertSee('5 min') // Build 300s
                        ->assertSee('2 min') // Test 120s
                        ->assertSee($startTime->format('F j, Y'))
                        ->assertSee($startTime->format('g:i:s A'))
                        ->assertSee($endTime->format('g:i:s A'))
                        ->assertSee($submitTime->format('g:i:s A'));
                });
        });
    }

    public function testBuildTimelineHidesMissingStages(): void
    {
        $startTime = Carbon::now()->subHours(2);
        $endTime = Carbon::now()->subHour();

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'starttime' => $startTime->toIso8601String(),
            'endtime' => $endTime->toIso8601String(),
            'configureerrors' => -1,
            'builderrors' => -1,
            'testfailed' => -1,
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->resize(1920, 1080)
                ->visit("/builds/{$build->id}")
                ->waitFor('@build-timeline')
                ->within('@build-timeline', function (Browser $timeline): void {
                    $timeline->waitForText('Start')
                        ->waitForText('End')
                        ->assertSee('Start')
                        ->assertSee('End')
                        ->assertDontSee('Configure')
                        ->assertDontSee('Build')
                        ->assertDontSee('Test')
                    ;
                });
        });
    }

    public function testBuildTimelineStatusColors(): void
    {
        $startTime = Carbon::now()->subHours(2);
        $endTime = Carbon::now()->subHour();

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'starttime' => $startTime->toIso8601String(),
            'endtime' => $endTime->toIso8601String(),
            'configureerrors' => 1,
            'configurewarnings' => 0,
            'builderrors' => 0,
            'buildwarnings' => 1,
            'testpassed' => 5,
            'testfailed' => 0,
            'testnotrun' => 0,
        ]);
        $build->configure()->create(Configure::factory()->make(['status' => 1])->toArray());

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->resize(1920, 1080)
                ->visit("/builds/{$build->id}")
                ->waitFor('@build-timeline')
                ->waitForText('Configure')
                ->waitForText('Build')
                ->waitForText('Test')
                ->within('@build-timeline', function (Browser $timeline): void {
                    // Start (index 1) - Configure (index 2) - Build (index 3) - Test (index 4) - End (index 5)
                    $timeline->within('[data-test="timeline-step"]:nth-child(2)', function (Browser $step): void {
                        $step->assertSee('Configure')
                            ->assertAttributeContains('@step-icon', 'class', 'tw-text-error');
                    });
                    $timeline->within('[data-test="timeline-step"]:nth-child(3)', function (Browser $step): void {
                        $step->assertSee('Build')
                            ->assertAttributeContains('@step-icon', 'class', 'tw-text-warning');
                    });
                    $timeline->within('[data-test="timeline-step"]:nth-child(4)', function (Browser $step): void {
                        $step->assertSee('Test')
                            ->assertAttributeContains('@step-icon', 'class', 'tw-text-success');
                    });
                });
        });
    }

    public function testBuildTimelineMobileLayout(): void
    {
        $startTime = Carbon::now()->subHours(2);
        $endTime = Carbon::now()->subHour();

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'starttime' => $startTime->toIso8601String(),
            'endtime' => $endTime->toIso8601String(),
            'configureerrors' => 0,
            'configurewarnings' => 0,
            'configureduration' => 60,
        ]);
        $build->configure()->create(Configure::factory()->make()->toArray());

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->resize(400, 800)
                ->visit("/builds/{$build->id}")
                ->waitFor('@build-timeline')
                ->waitFor('@timeline-step-mobile')
                ->waitForText('Configure')
                ->within('[data-test="timeline-step-mobile"]:nth-child(2)', function (Browser $step): void {
                    $step->assertSee('Configure')
                         ->assertSee('1 min');
                });
        });
    }

    public function testBuildTimelineLinks(): void
    {
        $startTime = Carbon::now()->subHours(2);
        $endTime = Carbon::now()->subHour();

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'starttime' => $startTime->toIso8601String(),
            'endtime' => $endTime->toIso8601String(),
            'configureerrors' => 0,
            'configurewarnings' => 0,
            'builderrors' => 0,
            'buildwarnings' => 0,
            'testpassed' => 5,
            'testfailed' => 0,
            'testnotrun' => 0,
        ]);
        $build->configure()->create(Configure::factory()->make()->toArray());

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->resize(1920, 1080)
                ->visit("/builds/{$build->id}")
                ->waitFor('@build-timeline')
                ->waitForText('Configure')
                ->waitForText('Build')
                ->waitForText('Test')
                ->within('@build-timeline', function (Browser $timeline) use ($build): void {
                    $timeline->within('[data-test="timeline-step"]:nth-child(2)', function (Browser $step) use ($build): void {
                        $step->waitFor('@step-label')
                            ->assertAttributeContains('@step-label', 'href', "/builds/{$build->id}/configure");
                    });
                    $timeline->within('[data-test="timeline-step"]:nth-child(3)', function (Browser $step) use ($build): void {
                        $step->waitFor('@step-label')
                            ->assertAttributeContains('@step-label', 'href', "/builds/{$build->id}/build");
                    });
                    $timeline->within('[data-test="timeline-step"]:nth-child(4)', function (Browser $step) use ($build): void {
                        $step->waitFor('@step-label')
                            ->assertAttributeContains('@step-label', 'href', "/builds/{$build->id}/tests");
                    });
                });
        });
    }

    public function testBuildTimelineZeroDuration(): void
    {
        $startTime = Carbon::now()->subHours(2);
        $endTime = Carbon::now()->subHour();

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'starttime' => $startTime->toIso8601String(),
            'endtime' => $endTime->toIso8601String(),
            'configureerrors' => 0,
            'configurewarnings' => 0,
            'configureduration' => 0,
        ]);
        $build->configure()->create(Configure::factory()->make()->toArray());

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->resize(1920, 1080)
                ->visit("/builds/{$build->id}")
                ->waitFor('@build-timeline')
                ->waitForText('0 sec')
                ->assertSeeIn('@build-timeline', '0 sec');
        });
    }

    public function testBuildHistoryFilters(): void
    {
        $startTime = Carbon::now()->subHour();
        $attributes = [
            'siteid' => $this->site->id,
            'name' => 'Linux-' . Str::uuid()->toString(),
            'type' => 'Nightly',
        ];

        /** @var Build $build */
        $build = $this->project->builds()->create(array_merge($attributes, [
            'uuid' => Str::uuid()->toString(),
            'starttime' => $startTime,
        ]));
        // The builds page only lists builds in a build group.
        $build->buildGroups()->attach($this->project->buildgroups()->where('name', 'Nightly')->firstOrFail());

        $otherSite = Site::factory()->create();
        $this->cleanup[] = $otherSite;

        // An earlier build like the current one, and earlier builds which differ from it in its site (one
        // build) or group (three), so that the number of builds shown tells which filters are applied.  A
        // later build and builds with another name are never shown.  The build like the current one is the
        // oldest, so that the history page's time range includes all the others.
        $builds = [
            ['starttime' => $startTime->copy()->subMinutes(8)],
            ['starttime' => $startTime->copy()->addMinute()],
            ['starttime' => $startTime->copy()->subMinutes(2), 'siteid' => $otherSite->id],
            ['starttime' => $startTime->copy()->subMinutes(3), 'name' => 'Other Build'],
            ['starttime' => $startTime->copy()->subMinutes(4), 'name' => 'Other Build'],
            ['starttime' => $startTime->copy()->subMinutes(5), 'type' => 'Experimental'],
            ['starttime' => $startTime->copy()->subMinutes(6), 'type' => 'Experimental'],
            ['starttime' => $startTime->copy()->subMinutes(7), 'type' => 'Experimental'],
        ];
        foreach ($builds as $differences) {
            /** @var Build $otherBuild */
            $otherBuild = $this->project->builds()->create(array_merge($attributes, ['uuid' => Str::uuid()->toString()], $differences));
            $otherBuild->buildGroups()->attach($this->project->buildgroups()->where('name', $otherBuild->type)->firstOrFail());
        }

        $this->browse(function (Browser $browser) use ($build): void {
            try {
                $browser->visit("/builds/{$build->id}")
                    ->waitFor('[data-test="build-time-chart"][data-test-point-count="2"]')
                    ->assertAttributeContains('@trend-filter-site', 'title', $this->site->name)
                    ->assertSelected('@trend-filter-site', 'this')
                    ->assertSelected('@trend-filter-group', 'this')
                    // The build history only includes builds with this build's name.
                    ->assertMissing('@trend-filter-name')
                    ->waitFor('[data-test="build-history-link"][href]')
                    ->assertAttributeContains('@build-history-link', 'href', 'filtercount=5&showfilters=1&filtercombine=and&field1=site&compare1=61')
                    ->select('@trend-filter-site', 'all')
                    ->waitFor('[data-test="build-time-chart"][data-test-point-count="3"]')
                    // The history link lists the same builds as the chart.
                    ->waitFor('[data-test="build-history-link"][href]')
                    ->waitForReload(fn (Browser $browser) => $browser->click('@build-history-link'))
                    ->waitFor('@build-row');
                self::assertCount(3, $browser->elements('@build-row'));

                $browser->visit("/builds/{$build->id}")
                    ->waitFor('[data-test="build-time-chart"][data-test-point-count="3"]')
                    ->select('@trend-filter-group', 'all')
                    ->waitFor('[data-test="build-time-chart"][data-test-point-count="6"]')
                    ->waitFor('[data-test="build-history-link"][href]')
                    ->assertAttributeContains('@build-history-link', 'href', 'filtercount=3&showfilters=1&filtercombine=and&field1=buildname&compare1=61')
                    // The choices are remembered.
                    ->refresh()
                    ->waitFor('[data-test="build-time-chart"][data-test-point-count="6"]')
                    ->assertSelected('@trend-filter-site', 'all')
                    ->assertSelected('@trend-filter-group', 'all')
                    ->select('@trend-filter-site', 'this')
                    ->waitFor('[data-test="build-time-chart"][data-test-point-count="5"]')
                    ->assertSelected('@trend-filter-site', 'this');
            } finally {
                $browser->script("try { localStorage.removeItem('cdash.trendFilters.build'); } catch {}");
            }
        });
    }

    /**
     * The history of a SubProject's build only includes that SubProject's builds.
     */
    public function testBuildHistoryForSubProjectBuild(): void
    {
        $nightly = $this->project->buildgroups()->where('name', 'Nightly')->firstOrFail();
        $subprojects = array_map(fn (): SubProject => $this->project->subprojects()->create([
            'name' => Str::uuid()->toString(),
            'groupid' => -1,
        ]), [0, 1]);

        $children = [];
        foreach ([20, 10] as $minutesAgo) {
            $attributes = [
                'siteid' => $this->site->id,
                'name' => 'Linux',
                'type' => 'Nightly',
                'starttime' => Carbon::now()->subMinutes($minutesAgo),
            ];
            /** @var Build $parent */
            $parent = $this->project->builds()->create(array_merge($attributes, ['uuid' => Str::uuid()->toString()]));
            $parent->buildGroups()->attach($nightly);
            foreach ($subprojects as $subproject) {
                /** @var Build $child */
                $child = $this->project->builds()->create(array_merge($attributes, [
                    'uuid' => Str::uuid()->toString(),
                    'parentid' => $parent->id,
                    'subprojectid' => $subproject->id,
                ]));
                $child->buildGroups()->attach($nightly);
                $children[] = $child;
            }
        }

        $this->browse(function (Browser $browser) use ($children, $subprojects): void {
            $browser->visit("/builds/{$children[2]->id}")
                ->waitFor('[data-test="build-time-chart"][data-test-point-count="2"]')
                ->waitFor('[data-test="build-history-link"][href]')
                ->assertAttributeContains('@build-history-link', 'href', '&subproject=' . rawurlencode($subprojects[0]->name) . '&')
                ->waitForReload(fn (Browser $browser) => $browser->click('@build-history-link'))
                ->waitFor('@build-row');
            self::assertCount(2, $browser->elements('@build-row'));
        });
    }

    public function testBuildHistoryLimit(): void
    {
        $startTime = Carbon::parse('2024-03-15T08:30:00Z');
        $attributes = [
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'type' => 'Nightly',
        ];

        /** @var Build $build */
        $build = $this->project->builds()->create(array_merge($attributes, [
            'uuid' => Str::uuid()->toString(),
            'starttime' => $startTime,
        ]));
        for ($i = 1; $i <= 25; $i++) {
            $this->project->builds()->create(array_merge($attributes, [
                'uuid' => Str::uuid()->toString(),
                'starttime' => $startTime->copy()->subMinutes($i),
            ]));
        }

        $this->browse(function (Browser $browser) use ($build): void {
            try {
                $browser->visit("/builds/{$build->id}")
                    ->waitFor('[data-test="build-time-chart"][data-test-point-count="26"]')
                    ->assertSelected('@trend-filter-limit', '100')
                    ->waitFor('[data-test="build-history-link"][href]')
                    ->assertAttributeContains('@build-history-link', 'href', 'compare4=83&value4=' . rawurlencode('2024-03-15T08:04:59Z'))
                    ->select('@trend-filter-limit', '25')
                    ->waitFor('[data-test="build-time-chart"][data-test-point-count="25"]')
                    // The history link starts at the oldest build in the chart.
                    ->waitFor('[data-test="build-history-link"][href]')
                    ->assertAttributeContains('@build-history-link', 'href', 'compare4=83&value4=' . rawurlencode('2024-03-15T08:05:59Z'))
                    // The choice is remembered.
                    ->refresh()
                    ->waitFor('[data-test="build-time-chart"][data-test-point-count="25"]')
                    ->assertSelected('@trend-filter-limit', '25');
            } finally {
                $browser->script("try { localStorage.removeItem('cdash.trendFilters.build'); } catch {}");
            }
        });
    }
}
