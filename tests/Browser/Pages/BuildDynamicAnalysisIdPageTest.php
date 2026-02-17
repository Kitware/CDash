<?php

namespace Tests\Browser\Pages;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Build;
use App\Models\DynamicAnalysis;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class BuildDynamicAnalysisIdPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use UpdatesSiteInformation;

    private Project $project;
    private Site $site;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->site = $this->makeSite();
        $this->updateSiteInfoIfChanged($this->site, new SiteInformation([]));
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

    /**
     * @return array{
     *     array{
     *         string, string
     *     }
     * }
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
