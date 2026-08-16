<?php

namespace Tests\Browser\Pages;

use App\Models\Build;
use App\Models\Configure;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
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

    public function testShowsHistoryLink(): void
    {
        $buildName = 'TestBuild_' . Str::uuid()->toString();
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

        $this->browse(function (Browser $browser) use ($build, $buildName, $buildType, $startTime): void {
            $browser->visit("/builds/{$build->id}")
                ->waitForText('Show History')
                ->assertAttributeContains('@build-history-link', 'href', 'project=' . urlencode($this->project->name))
                ->assertAttributeContains('@build-history-link', 'href', 'value1=' . urlencode($this->site->name))
                ->assertAttributeContains('@build-history-link', 'href', 'value2=' . $buildName)
                ->assertAttributeContains('@build-history-link', 'href', 'value3=' . $buildType)
                ->assertAttributeContains('@build-history-link', 'href', 'value4=' . $startTime)
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
}
