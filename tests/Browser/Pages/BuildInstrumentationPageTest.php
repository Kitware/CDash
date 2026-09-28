<?php

namespace Tests\Browser\Pages;

use App\Enums\BuildCommandType;
use App\Models\Build;
use App\Models\BuildCommand;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Services\SiteService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

class BuildInstrumentationPageTest extends BrowserTestCase
{
    use CreatesProjects;

    private Project $project;

    private Build $build;

    private Site $site;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->site = Site::factory()->create();
        SiteService::updateSiteInfoIfChanged($this->site, new SiteInformation([]));

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);
        $this->build = $build;
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->site->delete();

        parent::tearDown();
    }

    /**
     * @param array<string,string> $measurements
     */
    private function addCompileCommand(array $measurements, string $measurementType = 'numeric/integer'): BuildCommand
    {
        /** @var BuildCommand $command */
        $command = $this->build->commands()->create([
            'type' => BuildCommandType::COMPILE,
            'starttime' => Carbon::now(),
            'duration' => 12345,
            'command' => Str::random(10),
            'result' => '0',
            'source' => Str::random(10),
            'language' => 'C++',
            'config' => 'Release',
            'workingdirectory' => Str::uuid()->toString(),
        ]);

        foreach ($measurements as $name => $value) {
            $command->measurements()->create([
                'name' => $name,
                'type' => $measurementType,
                'value' => $value,
            ]);
        }

        return $command;
    }

    public function testHeatmapColorModeHiddenWithoutMaxRss(): void
    {
        $this->addCompileCommand(['BeforeHostMemoryUsed' => '2753536']);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/instrumentation")
                ->waitFor('@flame-chart-legend')
                ->assertSeeIn('@flame-chart-legend', 'COMPILE')
                ->assertMissing('@flame-chart-color-mode')
            ;
        });
    }

    public function testHeatmapColorModeHiddenWithNonNumericMaxRss(): void
    {
        $this->addCompileCommand(['MaxRSS' => '78144'], 'text/string');

        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/instrumentation")
                ->waitFor('@flame-chart-legend')
                ->assertMissing('@flame-chart-color-mode')
            ;
        });
    }

    public function testHeatmapColorModeShownWithMaxRss(): void
    {
        $this->addCompileCommand(['MaxRSS' => '78144']);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/instrumentation")
                ->waitFor('@flame-chart-color-mode')
                ->assertSelectHasOptions('@flame-chart-color-mode', ['category', 'heatmap'])
                ->assertSeeIn('@flame-chart-color-mode', 'MaxRSS')
                ->assertSeeIn('@flame-chart-legend', 'COMPILE')
                ->select('@flame-chart-color-mode', 'heatmap')
                ->waitUntilMissing('@flame-chart-legend')
                ->select('@flame-chart-color-mode', 'category')
                ->waitFor('@flame-chart-legend')
            ;
        });
    }
}
