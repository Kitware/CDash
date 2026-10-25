<?php

namespace Tests\Browser\Pages;

use App\Models\Build;
use App\Models\Coverage;
use App\Models\CoverageFile;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Services\SiteService;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

class CoverageFilePageTest extends BrowserTestCase
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

        $this->build = $this->createBuild();
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->site->delete();

        parent::tearDown();
    }

    private function createBuild(): Build
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);
        return $build;
    }

    private function createCoverage(Build $build, string $path): Coverage
    {
        $coverageFile = CoverageFile::firstOrCreate([
            'fullpath' => $path,
            'file' => Str::uuid()->toString(),
            'crc32' => 0,
        ]);

        /** @var Coverage $coverage */
        $coverage = $build->coverageResults()->create([
            'fileid' => $coverageFile->id,
            'locuntested' => 1,
            'loctested' => 1,
            'branchesuntested' => 0,
            'branchestested' => 0,
        ]);
        return $coverage;
    }

    public function testShowsFilePath(): void
    {
        $path = 'foo/' . Str::uuid()->toString() . '.cpp';
        $coverage = $this->createCoverage($this->build, $path);

        $this->browse(function (Browser $browser) use ($coverage, $path): void {
            $browser->visit("/builds/{$this->build->id}/coverage/{$coverage->id}")
                ->waitForText($path)
                ->assertSee($path)
            ;
        });
    }

    public function testShows404WhenFileDoesNotExist(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/coverage/123456789")
                ->assertSee('404')
            ;
        });
    }

    public function testShows404WhenFileBelongsToAnotherBuild(): void
    {
        $coverage = $this->createCoverage($this->createBuild(), 'foo/bar.cpp');

        $this->browse(function (Browser $browser) use ($coverage): void {
            $browser->visit("/builds/{$this->build->id}/coverage/{$coverage->id}")
                ->assertSee('404')
            ;
        });
    }
}
