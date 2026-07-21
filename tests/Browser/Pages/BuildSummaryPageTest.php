<?php

namespace Tests\Browser\Pages;

use App\Models\Build;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Services\SiteService;
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
}
