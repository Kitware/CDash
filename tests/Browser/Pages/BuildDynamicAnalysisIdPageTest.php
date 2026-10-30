<?php

namespace Tests\Browser\Pages;

use App\Models\Build;
use App\Models\DynamicAnalysis;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Services\SiteService;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

class BuildDynamicAnalysisIdPageTest extends BrowserTestCase
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

        /** @var DynamicAnalysis $da */
        $da = $build->dynamicAnalyses()->save(DynamicAnalysis::factory()->make());

        $this->browse(function (Browser $browser) use ($da, $build): void {
            $browser->visit("/builds/{$build->id}/dynamic_analysis/{$da->id}")
                ->waitForText($build->name)
                ->assertSee($build->name)
            ;
        });
    }

    public function testShowsLog(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var DynamicAnalysis $da */
        $da = $build->dynamicAnalyses()->save(DynamicAnalysis::factory()->make());

        $this->browse(function (Browser $browser) use ($da, $build): void {
            $browser->visit("/builds/{$build->id}/dynamic_analysis/{$da->id}")
                ->waitForText($da->log)
                ->assertSee($da->log)
            ;
        });
    }

    public function testShowsName(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var DynamicAnalysis $da */
        $da = $build->dynamicAnalyses()->save(DynamicAnalysis::factory()->make());

        $this->browse(function (Browser $browser) use ($da, $build): void {
            $browser->visit("/builds/{$build->id}/dynamic_analysis/{$da->id}")
                ->waitForText($da->name)
                ->assertSee($da->name)
            ;
        });
    }

    public function testShows404WhenDynamicAnalysisDoesNotExist(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}/dynamic_analysis/123456789")
                ->assertSee('404')
            ;
        });
    }

    public function testShows404WhenDynamicAnalysisBelongsToAnotherBuild(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Build $otherBuild */
        $otherBuild = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var DynamicAnalysis $da */
        $da = $otherBuild->dynamicAnalyses()->save(DynamicAnalysis::factory()->make());

        $this->browse(function (Browser $browser) use ($da, $build): void {
            $browser->visit("/builds/{$build->id}/dynamic_analysis/{$da->id}")
                ->assertSee('404')
            ;
        });
    }

    /**
     * @return array<array{
     *     string, string,
     * }>
     */
    public static function statuses(): array
    {
        return [
            ['passed', 'Passed'],
            ['notrun', 'Not Run'],
            ['failed', 'Failed'],
        ];
    }

    #[DataProvider('statuses')]
    public function testShowsStatus(string $dbValue, string $displayText): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var DynamicAnalysis $da */
        $da = $build->dynamicAnalyses()->save(DynamicAnalysis::factory()->make([
            'status' => $dbValue,
        ]));

        $this->browse(function (Browser $browser) use ($displayText, $da, $build): void {
            $browser->visit("/builds/{$build->id}/dynamic_analysis/{$da->id}")
                ->waitForText($displayText)
                ->assertSee($displayText)
            ;
        });
    }
}
