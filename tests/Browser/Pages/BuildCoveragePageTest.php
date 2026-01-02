<?php

namespace Tests\Browser\Pages;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Build;
use App\Models\Coverage;
use App\Models\CoverageFile;
use App\Models\CoverageView;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class BuildCoveragePageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use UpdatesSiteInformation;

    private Project $project;
    private Build $build;
    private Site $site;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->site = $this->makeSite();
        $this->updateSiteInfoIfChanged($this->site, new SiteInformation([]));

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
        parent::tearDown();

        $this->project->delete();
        $this->site->delete();
    }

    private function createCoverage(
        int $locTested,
        int $locUntested,
        int $branchesTested,
        int $branchesUntested,
        string $path,
    ): CoverageView {
        $coverageFile = CoverageFile::firstOrCreate([
            'fullpath' => $path,
            'file' => Str::uuid()->toString(),
            'crc32' => 0,
        ]);

        /** @var Coverage $coverage */
        $coverage = $this->build->coverageResults()->create([
            'fileid' => $coverageFile->id,
            'locuntested' => $locUntested,
            'loctested' => $locTested,
            'branchesuntested' => $branchesUntested,
            'branchestested' => $branchesTested,
        ]);

        return CoverageView::findOrFail($coverage->id);
    }

    public function testShowsBuildName(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/coverage")
                ->waitForText($this->build->name)
                ->assertSee($this->build->name)
            ;
        });
    }

    /**
     * Navigates up and down the directory structure, verifying:
     *
     * 1. Navigation works
     * 2. Directory percent and absolute line counts are correct
     * 3. Breadcrumbs are correct
     * 4. Folder vs File icons are correct
     */
    public function testDirectoryNavigation(): void
    {
        $this->createCoverage(1, 1, 0, 0, 'foo/bar/baz.cpp');

        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/coverage")
                ->waitFor('@coverage-directory-link')
                ->assertSeeIn('@breadcrumbs', $this->project->name)
                ->assertDontSeeIn('@breadcrumbs', 'foo')
                ->assertDontSeeIn('@breadcrumbs', 'bar')
                ->assertDontSeeIn('@breadcrumbs', 'baz.cpp')
                ->assertSeeIn('@coverage-table', 'foo')
                ->assertDontSeeIn('@coverage-table', 'bar')
                ->assertDontSeeIn('@coverage-table', 'baz.cpp')
                ->assertSeeIn('@coverage-table', '50.0%')
                ->assertSeeIn('@coverage-table', '1 / 2')
                ->assertVisible('@coverage-directory-link')
                ->assertMissing('@coverage-file-link')
                ->click('@coverage-directory-link')
                ->assertSeeIn('@breadcrumbs', $this->project->name)
                ->assertSeeIn('@breadcrumbs', 'foo')
                ->assertDontSeeIn('@breadcrumbs', 'bar')
                ->assertDontSeeIn('@breadcrumbs', 'baz.cpp')
                ->assertDontSeeIn('@coverage-table', 'foo')
                ->assertSeeIn('@coverage-table', 'bar')
                ->assertDontSeeIn('@coverage-table', 'baz.cpp')
                ->assertSeeIn('@coverage-table', '50.0%')
                ->assertSeeIn('@coverage-table', '1 / 2')
                ->assertVisible('@coverage-directory-link')
                ->assertMissing('@coverage-file-link')
                ->click('@coverage-directory-link')
                ->assertSeeIn('@breadcrumbs', $this->project->name)
                ->assertSeeIn('@breadcrumbs', 'foo')
                ->assertSeeIn('@breadcrumbs', 'bar')
                ->assertDontSeeIn('@breadcrumbs', 'baz.cpp')
                ->assertDontSeeIn('@coverage-table', 'foo')
                ->assertDontSeeIn('@coverage-table', 'bar')
                ->assertSeeIn('@coverage-table', 'baz.cpp')
                ->assertSeeIn('@coverage-table', '50.0%')
                ->assertSeeIn('@coverage-table', '1 / 2')
                ->assertMissing('@coverage-directory-link')
                ->assertVisible('@coverage-file-link')
            ;
        });
    }

    public function testBackButton(): void
    {
        $this->createCoverage(1, 1, 0, 0, 'foo/bar/baz.cpp');

        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/coverage")
                ->waitFor('@coverage-directory-link')
                ->assertSeeIn('@coverage-table', 'foo')
                ->assertDontSeeIn('@coverage-table', 'bar')
                ->assertDontSeeIn('@coverage-table', 'baz.cpp')
                ->click('@coverage-directory-link')
                ->assertDontSeeIn('@coverage-table', 'foo')
                ->assertSeeIn('@coverage-table', 'bar')
                ->assertDontSeeIn('@coverage-table', 'baz.cpp')
                ->click('@breadcrumbs-back-button')
                ->assertSeeIn('@coverage-table', 'foo')
                ->assertDontSeeIn('@coverage-table', 'bar')
                ->assertDontSeeIn('@coverage-table', 'baz.cpp')
                ->assertSeeIn('@coverage-table', 'foo')
                ->assertDontSeeIn('@coverage-table', 'bar')
                ->assertDontSeeIn('@coverage-table', 'baz.cpp')
            ;
        });
    }

    public function testBranchCoverage(): void
    {
        $this->createCoverage(1, 1, 0, 0, Str::uuid()->toString());

        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/coverage")
                ->waitFor('@line-coverage-summary')
                ->assertMissing('@branch-coverage-summary')
                ->assertDontSeeIn('@coverage-table', 'Branches Tested')
            ;
        });

        $this->createCoverage(1, 1, 3, 2, Str::uuid()->toString());

        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/coverage")
                ->waitFor('@line-coverage-summary')
                ->assertSeeIn('@branch-coverage-summary', '60.00% (3 / 5)')
                ->assertSeeIn('@coverage-table', 'Branches Tested')
            ;
        });
    }

    public function testFilters(): void
    {
        $filename1 = Str::uuid()->toString();
        $this->createCoverage(1, 1, 0, 0, $filename1);
        $filename2 = Str::uuid()->toString();
        $this->createCoverage(1, 1, 0, 0, $filename2);

        $this->browse(function (Browser $browser) use ($filename2, $filename1): void {
            $browser->visit("/builds/{$this->build->id}/coverage")
                ->waitFor('@coverage-table')
                ->assertSeeIn('@coverage-table', $filename1)
                ->assertSeeIn('@coverage-table', $filename2)
            ;

            // Now, filter by filename=filename1...
            $browser->visit("/builds/{$this->build->id}/coverage?filters=%7B%22all%22%3A%5B%7B%22eq%22%3A%7B%22filePath%22%3A%22{$filename1}%22%7D%7D%5D%7D")
                ->waitFor('@coverage-table')
                ->assertSeeIn('@coverage-table', $filename1)
                ->assertDontSeeIn('@coverage-table', $filename2)
            ;
        });
    }
}
